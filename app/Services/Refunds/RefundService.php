<?php

namespace App\Services\Refunds;

use App\Models\Order;
use App\Models\OrderLog;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Services\Inventory\InventoryService;
use App\Services\Payment\Factories\PaymentGatewayFactory;
use App\Services\Wallet\UserWalletService;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RefundService
{
    public function __construct(
        private PaymentGatewayFactory $gatewayFactory,
        private UserWalletService $wallets,
        private InventoryService $inventory,
        private NotificationService $notifications,
    ) {}

    public function request(Order $order, float $amount, string $reason, array $options = []): Refund
    {
        $amount = round($amount, 4);
        if ($amount <= 0) throw new \InvalidArgumentException('قيمة الاسترداد يجب أن تكون أكبر من صفر');

        $idempotency = $options['idempotency_key'] ?? 'refund_' . $order->id . '_' . Str::uuid();
        $refund = DB::transaction(function () use ($order, $amount, $reason, $options, $idempotency) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status_payment !== 'paid' && !in_array($locked->status_payment, ['partially_refunded', 'refunded'], true)) {
                throw new \RuntimeException('لا يمكن استرداد طلب غير مدفوع');
            }

            $remaining = max(0, (float) $locked->total_amount - (float) ($locked->refunded_amount ?? 0));
            if ($amount > $remaining + 0.0001) {
                throw new \RuntimeException('قيمة الاسترداد تتجاوز المبلغ القابل للاسترداد');
            }

            $existing = Refund::where('idempotency_key', $idempotency)->first();
            if ($existing) return $existing;

            $tx = PaymentTransaction::where('order_id', $locked->id)
                ->whereIn('status', ['paid', 'finished', 'confirmed', 'success'])
                ->latest('id')->first()
                ?: PaymentTransaction::where('order_id', $locked->id)->latest('id')->first();

            $type = abs($amount - $remaining) < 0.0001 && (float) ($locked->refunded_amount ?? 0) == 0.0 ? 'full' : 'partial';
            return Refund::create([
                'order_id' => $locked->id,
                'payment_transaction_id' => $tx?->id,
                'requested_by_admin_id' => $options['admin_id'] ?? auth('admin')->id(),
                'gateway' => (($options['destination_wallet'] ?? false) ? 'wallet' : ($tx?->gateway ?: $locked->provider)),
                'type' => $type,
                'status' => Refund::STATUS_REQUESTED,
                'amount' => $amount,
                'currency' => $tx?->original_currency ?: config('commerce.base_currency', 'EGP'),
                'reason' => $reason,
                'idempotency_key' => $idempotency,
            ]);
        }, 3);

        if ($refund->status === Refund::STATUS_REFUNDED || $refund->status === Refund::STATUS_PENDING) return $refund;
        return $this->process($refund, $options);
    }

    public function process(Refund $refund, array $options = []): Refund
    {
        $refund = DB::transaction(function () use ($refund) {
            $locked = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [Refund::STATUS_REFUNDED, Refund::STATUS_PENDING], true)) return $locked;
            $locked->forceFill(['status' => Refund::STATUS_PROCESSING, 'failure_reason' => null])->save();
            return $locked;
        }, 3);

        if (in_array($refund->status, [Refund::STATUS_REFUNDED, Refund::STATUS_PENDING], true)) return $refund;

        try {
            $order = $refund->order()->with(['user', 'items.product'])->firstOrFail();
            $tx = $refund->paymentTransaction;
            $destinationWallet = (bool) ($options['destination_wallet'] ?? ($refund->gateway === 'wallet'));

            if ($destinationWallet) {
                if (!$order->user) throw new \RuntimeException('لا يوجد مستخدم لإضافة الاسترداد إلى المحفظة');
                $entry = $this->wallets->creditRefund($order->user, (float) $refund->amount, (string) $refund->id, [
                    'order_id' => $order->id,
                    'refund_id' => $refund->id,
                    'reason' => $refund->reason,
                ]);
                $result = ['success' => true, 'refund_id' => 'WALLET-' . $entry->id, 'status' => 'refunded'];
            } else {
                if (!$tx) throw new \RuntimeException('لا توجد معاملة دفع مرتبطة بالطلب');
                $providerTransactionId = $tx->provider_payment_id ?: $tx->purchase_id;
                if (!$providerTransactionId) throw new \RuntimeException('رقم معاملة مزود الدفع غير موجود');
                if (!in_array($tx->gateway, ['paymob', 'tamara', 'tabby'], true)) {
                    throw new \RuntimeException('بوابة الدفع الحالية لا تدعم استردادًا آليًا. استخدم الاسترداد إلى المحفظة أو عالجه يدويًا.');
                }
                $gateway = $this->gatewayFactory->make($tx->gateway);
                $result = $gateway->refund((string) $providerTransactionId, (float) $refund->amount, (string) $refund->reason);
            }

            if (!($result['success'] ?? false)) {
                throw new \RuntimeException($result['error'] ?? 'فشل تنفيذ الاسترداد لدى مزود الدفع');
            }

            $providerStatus = strtolower((string) ($result['refund_status'] ?? $result['status'] ?? 'refunded'));
            $asyncPending = in_array($providerStatus, ['pending', 'processing', 'created', 'refund_pending'], true);
            $finalStatus = $asyncPending ? Refund::STATUS_PENDING : Refund::STATUS_REFUNDED;

            DB::transaction(function () use ($refund, $order, $result, $finalStatus) {
                $lockedRefund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($lockedRefund->status === Refund::STATUS_REFUNDED) return;

                $lockedRefund->forceFill([
                    'status' => $finalStatus,
                    'provider_reference' => $result['refund_id'] ?? $result['transaction_id'] ?? null,
                    'provider_response' => $this->safeProviderResponse($result),
                    'processed_at' => now(),
                    'failure_reason' => null,
                ])->save();

                if ($finalStatus === Refund::STATUS_PENDING) {
                    // A pending gateway response is not money returned yet. Never inflate refunded_amount early.
                    $lockedOrder->forceFill(['refund_status' => 'refund_pending'])->save();
                } else {
                    $newRefunded = min((float) $lockedOrder->total_amount, (float) ($lockedOrder->refunded_amount ?? 0) + (float) $lockedRefund->amount);
                    $fullyRefunded = $newRefunded + 0.0001 >= (float) $lockedOrder->total_amount;
                    $lockedOrder->forceFill([
                        'refunded_amount' => $newRefunded,
                        'refund_status' => $fullyRefunded ? 'refunded' : 'partially_refunded',
                        'status_payment' => $fullyRefunded ? 'refunded' : 'partially_refunded',
                    ])->save();
                }
            }, 3);

            $refund->refresh();
            if ($refund->status === Refund::STATUS_REFUNDED) {
                $this->applyInventoryPolicy($refund, $options['items'] ?? null);
            }
            $this->log($refund, 'refund_processed', ['status' => $refund->status, 'amount' => $refund->amount]);
            $order->refresh();
            if ($order->user) {
                $this->notifications->user($order->user, 'تحديث الاسترداد', 'حالة استرداد الطلب ' . $order->order_number . ': ' . $refund->status, 'refund_status', ['order_id'=>$order->id,'refund_id'=>$refund->id,'status'=>$refund->status], true);
            }
            return $refund;
        } catch (\Throwable $e) {
            $refund->forceFill(['status' => Refund::STATUS_FAILED, 'failure_reason' => mb_substr($e->getMessage(), 0, 2000), 'processed_at' => now()])->save();
            $this->log($refund, 'refund_failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function finalizeAsync(Refund $refund, bool $succeeded, ?string $providerReference = null, array $providerResponse = []): Refund
    {
        if ($refund->status === Refund::STATUS_REFUNDED) return $refund;
        if (!$succeeded) {
            $refund->forceFill(['status'=>Refund::STATUS_FAILED,'failure_reason'=>'Provider reported refund failure','provider_response'=>$this->safeProviderResponse($providerResponse),'processed_at'=>now()])->save();
            $this->log($refund,'refund_failed',['provider_reference'=>$providerReference]);
            return $refund->fresh();
        }
        DB::transaction(function () use ($refund,$providerReference,$providerResponse) {
            $r=Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($r->status===Refund::STATUS_REFUNDED) return;
            $o=Order::whereKey($r->order_id)->lockForUpdate()->firstOrFail();
            $new=min((float)$o->total_amount,(float)($o->refunded_amount??0)+(float)$r->amount);
            $full=$new+0.0001>=(float)$o->total_amount;
            $r->forceFill(['status'=>Refund::STATUS_REFUNDED,'provider_reference'=>$providerReference?:$r->provider_reference,'provider_response'=>$this->safeProviderResponse($providerResponse),'processed_at'=>now(),'failure_reason'=>null])->save();
            $o->forceFill(['refunded_amount'=>$new,'refund_status'=>$full?'refunded':'partially_refunded','status_payment'=>$full?'refunded':'partially_refunded'])->save();
        },3);
        $refund->refresh(); $this->applyInventoryPolicy($refund, null); $this->log($refund,'refund_confirmed',['amount'=>$refund->amount]);
        return $refund;
    }

    private function applyInventoryPolicy(Refund $refund, ?array $items): void
    {
        $order = $refund->order()->with('items.product')->firstOrFail();
        if ($items === null && $refund->type !== 'full') return;

        $quantities = [];
        if (is_array($items)) {
            foreach ($items as $row) {
                if (isset($row['order_item_id'], $row['quantity'])) $quantities[(int) $row['order_item_id']] = max(0, (int) $row['quantity']);
            }
        }

        foreach ($order->items as $item) {
            $qty = $items === null ? max(0, (int) $item->quantity - (int) $item->refunded_quantity) : ($quantities[$item->id] ?? 0);
            if ($qty <= 0) continue;
            $qty = min($qty, max(0, (int) $item->quantity - (int) $item->refunded_quantity));
            $this->inventory->restoreForRefund($item, $qty, 'REFUND-' . $refund->id . '-ITEM-' . $item->id);
        }
    }

    private function safeProviderResponse(array $result): array
    {
        unset($result['raw_response'], $result['token'], $result['authorization']);
        return $result;
    }

    private function log(Refund $refund, string $action, array $data): void
    {
        OrderLog::create([
            'order_id' => $refund->order_id,
            'action' => $action,
            'provider' => $refund->gateway,
            'request_data' => array_merge($data, ['refund_id' => $refund->id]),
            'response_data' => null,
            'error_message' => $refund->failure_reason,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_by' => auth('admin')->id(),
        ]);
    }
}
