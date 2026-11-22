<?php

namespace App\Services\Fulfillment;

use App\Models\Like4AppPurchase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderLog;
use App\Services\Like4AppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Like4AppFulfillmentService
{
    public function __construct(private Like4AppService $provider) {}

    public function fulfillOrder(Order $order, bool $manualRetry = false): array
    {
        $order->loadMissing('items.product');
        $results = [];
        $hasProviderItems = false;
        $allFulfilled = true;

        foreach ($order->items as $item) {
            $source = (string) ($item->fulfillment_source_snapshot ?: optional($item->product)->fulfillment_source ?: (optional($item->product)->external_id ? 'like4app' : 'none'));
            if ($source !== 'like4app') continue;
            $providerProductId = $item->provider_product_id ?: optional($item->product)->external_id;
            if (!$providerProductId) continue;

            $hasProviderItems = true;
            $quantity = max(1, (int) $item->quantity);
            for ($unit = 1; $unit <= $quantity; $unit++) {
                $result = $this->fulfillUnit($order, $item, (string) $providerProductId, $unit, $manualRetry);
                $results[] = $result;
                if (($result['status'] ?? null) !== Like4AppPurchase::STATUS_FULFILLED) {
                    $allFulfilled = false;
                }
            }

            $this->syncOrderItemFromPurchases($item);
        }

        if ($hasProviderItems) {
            $order->forceFill([
                'provider' => 'like4app',
                'fulfillment_status' => $allFulfilled ? 'fulfilled' : 'processing',
            ])->save();
        }

        return [
            'success' => !$hasProviderItems || $allFulfilled,
            'has_provider_items' => $hasProviderItems,
            'results' => $results,
        ];
    }

    public function retryItem(OrderItem $item): array
    {
        $order = $item->order()->firstOrFail();
        $source = (string) ($item->fulfillment_source_snapshot ?: optional($item->product)->fulfillment_source ?: (optional($item->product)->external_id ? 'like4app' : 'none'));
        if ($source !== 'like4app') {
            throw new \RuntimeException('هذا العنصر ليس مضبوطاً للتنفيذ عبر Like4App');
        }
        $providerProductId = $item->provider_product_id ?: optional($item->product)->external_id;
        if (!$providerProductId) {
            throw new \RuntimeException('هذا العنصر غير مربوط بمنتج Like4App');
        }

        $results = [];
        for ($unit = 1; $unit <= max(1, (int) $item->quantity); $unit++) {
            $purchase = Like4AppPurchase::where('order_item_id', $item->id)->where('unit_index', $unit)->first();
            if ($purchase && $purchase->status === Like4AppPurchase::STATUS_FULFILLED) continue;
            $results[] = $this->fulfillUnit($order, $item, (string) $providerProductId, $unit, true);
        }
        $this->syncOrderItemFromPurchases($item);

        return ['success' => collect($results)->every(fn ($r) => ($r['status'] ?? null) === Like4AppPurchase::STATUS_FULFILLED), 'results' => $results];
    }

    private function fulfillUnit(Order $order, OrderItem $item, string $providerProductId, int $unitIndex, bool $manualRetry): array
    {
        $reference = 'L4A-' . $order->order_number . '-' . $item->id . '-' . $unitIndex;

        [$purchase, $executeProvider] = DB::transaction(function () use ($order, $item, $providerProductId, $unitIndex, $reference, $manualRetry) {
            $purchase = Like4AppPurchase::firstOrCreate(
                ['order_item_id' => $item->id, 'unit_index' => $unitIndex],
                [
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'provider_product_id' => $providerProductId,
                    'stable_reference' => $reference,
                    'status' => Like4AppPurchase::STATUS_PENDING,
                    'requested_quantity' => 1,
                ]
            );

            $purchase = Like4AppPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($purchase->status === Like4AppPurchase::STATUS_FULFILLED) {
                return [$purchase, false];
            }

            // A recent processing attempt owns this stable reference. Returning without another
            // provider call prevents two workers/web requests from buying the same voucher.
            if (!$manualRetry
                && $purchase->status === Like4AppPurchase::STATUS_PROCESSING
                && $purchase->last_attempt_at?->gt(now()->subMinutes(5))) {
                return [$purchase, false];
            }

            $purchase->forceFill([
                'status' => Like4AppPurchase::STATUS_PROCESSING,
                'attempts' => (int) $purchase->attempts + 1,
                'last_attempt_at' => now(),
                'failure_reason' => null,
            ])->save();

            return [$purchase->fresh(), true];
        }, 3);

        if (!$executeProvider) {
            return $this->purchaseResult($purchase);
        }

        try {
            $response = $this->provider->createOrder($providerProductId, 1, $reference);
            $data = $response['data'] ?? null;
            $providerSuccess = (bool) ($response['success'] ?? false)
                && is_array($data)
                && (int) ($data['response'] ?? 0) === 1;

            $serials = $providerSuccess ? $this->provider->parseSerialCodes($data) : [];
            $voucherCodes = array_values(array_filter(array_map(fn ($serial) => $serial['serial_code'] ?? null, $serials)));
            $providerTransactionId = $data['orderId'] ?? $data['transactionId'] ?? null;

            DB::transaction(function () use ($purchase, $response, $data, $providerSuccess, $serials, $voucherCodes, $providerTransactionId) {
                $locked = Like4AppPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === Like4AppPurchase::STATUS_FULFILLED) return;

                if ($providerSuccess && count($serials) >= 1) {
                    $locked->forceFill([
                        'status' => Like4AppPurchase::STATUS_FULFILLED,
                        'fulfilled_quantity' => 1,
                        'provider_transaction_id' => $providerTransactionId,
                        'serial_codes' => $serials,
                        'voucher_codes' => $voucherCodes,
                        'provider_response' => $this->provider->redactSensitive($data),
                        'failure_reason' => null,
                        'fulfilled_at' => now(),
                    ])->save();
                } elseif ($providerSuccess) {
                    $locked->forceFill([
                        'status' => Like4AppPurchase::STATUS_PARTIAL,
                        'provider_transaction_id' => $providerTransactionId,
                        'provider_response' => $this->provider->redactSensitive($data),
                        'failure_reason' => 'Provider reported success but no serial/voucher was returned',
                    ])->save();
                } else {
                    $message = $response['error'] ?? ($data['message'] ?? null) ?? ($data['errorCode'] ?? null) ?? 'Like4App purchase failed';
                    $locked->forceFill([
                        'status' => Like4AppPurchase::STATUS_FAILED,
                        'provider_response' => $this->provider->redactSensitive(is_array($data) ? $data : ['status_code' => $response['status_code'] ?? null]),
                        'failure_reason' => mb_substr((string) $message, 0, 2000),
                    ])->save();
                }
            }, 3);

            $purchase->refresh();
            $this->logPurchase($order, $item, $purchase);
            return $this->purchaseResult($purchase);
        } catch (\Throwable $e) {
            Like4AppPurchase::whereKey($purchase->id)->where('status', Like4AppPurchase::STATUS_PROCESSING)->update([
                'status' => Like4AppPurchase::STATUS_FAILED,
                'failure_reason' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            Log::error('Like4App fulfillment failed', [
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'reference' => $reference,
                'error_class' => $e::class,
            ]);
            return ['success' => false, 'status' => Like4AppPurchase::STATUS_FAILED, 'reference' => $reference, 'error' => 'Like4App fulfillment failed'];
        }
    }

    private function syncOrderItemFromPurchases(OrderItem $item): void
    {
        $purchases = Like4AppPurchase::where('order_item_id', $item->id)->orderBy('unit_index')->get();
        if ($purchases->isEmpty()) return;

        $serials = $purchases->flatMap(fn ($p) => $p->serial_codes ?? [])->values()->all();
        $vouchers = $purchases->flatMap(fn ($p) => $p->voucher_codes ?? [])->filter()->values()->all();
        $allFulfilled = $purchases->count() >= max(1, (int) $item->quantity)
            && $purchases->every(fn ($p) => $p->status === Like4AppPurchase::STATUS_FULFILLED);
        $hasFailed = $purchases->contains(fn ($p) => in_array($p->status, [Like4AppPurchase::STATUS_FAILED, Like4AppPurchase::STATUS_PARTIAL], true));

        $item->forceFill([
            'provider_product_id' => $item->provider_product_id ?: optional($item->product)->external_id,
            'provider_reference' => $purchases->first()->stable_reference,
            'provider_transaction_id' => $purchases->pluck('provider_transaction_id')->filter()->implode(','),
            'provider_status' => $allFulfilled ? 'fulfilled' : ($hasFailed ? 'failed' : 'processing'),
            'serial_codes' => $serials,
            'voucher_code' => count($vouchers) === 1 ? $vouchers[0] : null,
            'fulfilled_at' => $allFulfilled ? now() : null,
            'failure_reason' => $hasFailed ? $purchases->pluck('failure_reason')->filter()->implode(' | ') : null,
            'provider_data' => array_merge(is_array($item->provider_data) ? $item->provider_data : [], [
                'like4app_purchase_ids' => $purchases->pluck('id')->all(),
                'voucher_count' => count($vouchers),
            ]),
        ])->save();
    }

    private function logPurchase(Order $order, OrderItem $item, Like4AppPurchase $purchase): void
    {
        OrderLog::create([
            'order_id' => $order->id,
            'action' => 'like4app_fulfillment',
            'provider' => 'like4app',
            'request_data' => [
                'order_item_id' => $item->id,
                'reference' => $purchase->stable_reference,
                'provider_product_id' => $purchase->provider_product_id,
                'attempt' => $purchase->attempts,
            ],
            'response_data' => [
                'status' => $purchase->status,
                'provider_transaction_id' => $purchase->provider_transaction_id,
                'serial_count' => count($purchase->serial_codes ?? []),
                'voucher_count' => count($purchase->voucher_codes ?? []),
            ],
            'error_message' => $purchase->failure_reason,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_by' => auth('admin')->id(),
        ]);
    }

    private function purchaseResult(Like4AppPurchase $purchase): array
    {
        return [
            'success' => $purchase->status === Like4AppPurchase::STATUS_FULFILLED,
            'purchase_id' => $purchase->id,
            'status' => $purchase->status,
            'reference' => $purchase->stable_reference,
            'attempts' => $purchase->attempts,
            'failure_reason' => $purchase->failure_reason,
        ];
    }
}
