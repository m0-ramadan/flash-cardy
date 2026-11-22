<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Services\Coupons\CouponReservationService;
use App\Services\Inventory\InventoryService;
use App\Services\Payment\CompletePaidOrderService;
use App\Services\Payment\NowPaymentsService;
use App\Support\PaymentLogger;
use App\Support\SensitiveDataRedactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;

class NowPaymentsIpnController extends Controller
{
    public function __invoke(
        Request $request,
        NowPaymentsService $nowPayments,
        CompletePaidOrderService $completePaidOrder,
        InventoryService $inventory,
        CouponReservationService $coupons,
    ) {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response('Malformed JSON', 400);
        }
        if (!is_array($payload)) return response('Malformed JSON', 400);

        $signature = (string) $request->header('x-nowpayments-sig', '');
        if ($signature === '') return response('Missing signature', 401);
        if (!$nowPayments->verifyIpnSignature($payload, $signature)) {
            PaymentLogger::warning('Invalid NOWPayments IPN signature', ['ip' => $request->ip()]);
            return response('Invalid signature', 401);
        }

        $providerPaymentId = (string) ($payload['payment_id'] ?? '');
        $providerOrderId = (string) ($payload['order_id'] ?? '');
        if ($providerPaymentId === '' && $providerOrderId === '') return response('OK', 200);

        $eventKey = 'nowpayments:'.($providerPaymentId !== '' ? $providerPaymentId : $providerOrderId).':'.(string)($payload['payment_status'] ?? 'unknown');
        $event = PaymentWebhookEvent::firstOrCreate(
            ['gateway' => 'nowpayments', 'event_key' => $eventKey],
            [
                'provider_transaction_id' => $providerPaymentId ?: null,
                'payload_hash' => hash('sha256', $request->getContent()),
                'status' => 'received',
                'payload' => SensitiveDataRedactor::redact($payload),
            ]
        );

        $action = DB::transaction(function () use ($event, $payload, $providerPaymentId, $providerOrderId, $nowPayments) {
            $lockedEvent = PaymentWebhookEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($lockedEvent->processed_at) return ['action' => 'duplicate'];

            $payment = null;
            if ($providerPaymentId !== '') {
                $payment = PaymentTransaction::where(function ($q) use ($providerPaymentId) {
                    $q->where('provider_payment_id', $providerPaymentId)
                      ->orWhere('provider_transaction_id', $providerPaymentId);
                })->lockForUpdate()->first();
            }
            if (!$payment && $providerOrderId !== '') {
                $payment = PaymentTransaction::query()
                    ->where('gateway', 'nowpayments')
                    ->whereHas('order', fn ($q) => $q->where('order_number', $providerOrderId))
                    ->latest()->lockForUpdate()->first();
            }

            if (!$payment) {
                $lockedEvent->update(['status' => 'ignored_unknown', 'processed_at' => now()]);
                return ['action' => 'unknown'];
            }

            $order = $payment->order()->lockForUpdate()->first();
            if (!$order) {
                $lockedEvent->update(['payment_transaction_id' => $payment->id, 'status' => 'ignored_unknown_order', 'processed_at' => now()]);
                return ['action' => 'unknown'];
            }

            $providerStatus = (string) ($payload['payment_status'] ?? 'unknown');
            $normalizedStatus = $nowPayments->normalizeStatus($providerStatus);
            $manual = (bool) $payment->requires_manual_review;

            if (($payload['parent_payment_id'] ?? null) !== null) $manual = true;
            if ($providerOrderId !== '' && $providerOrderId !== (string) $order->order_number) $manual = true;

            if ($payment->price_currency && isset($payload['price_currency']) && strcasecmp((string)$payload['price_currency'], (string)$payment->price_currency) !== 0) $manual = true;
            if ($payment->pay_currency && isset($payload['pay_currency']) && strcasecmp((string)$payload['pay_currency'], (string)$payment->pay_currency) !== 0) $manual = true;

            if (isset($payload['price_amount']) && function_exists('bccomp')) {
                if (bccomp((string)$payload['price_amount'], (string)$payment->price_amount, 4) !== 0) $manual = true;
            } elseif (isset($payload['price_amount']) && abs((float)$payload['price_amount'] - (float)$payment->price_amount) > 0.0001) {
                $manual = true;
            }

            $actuallyPaid = (string) ($payload['actually_paid'] ?? $payment->actually_paid ?? '0');
            $payAmount = (string) ($payload['pay_amount'] ?? $payment->pay_amount ?? '0');
            $isUnderpaid = function_exists('bccomp') ? bccomp($actuallyPaid, $payAmount, 20) < 0 : ((float)$actuallyPaid + 1e-12 < (float)$payAmount);
            if ($normalizedStatus === 'paid' && $isUnderpaid) {
                $normalizedStatus = 'partially_paid';
                $manual = true;
            }

            $payment->forceFill([
                'provider_payment_id' => $providerPaymentId ?: $payment->provider_payment_id,
                'provider_transaction_id' => $providerPaymentId ?: $payment->provider_transaction_id,
                'status' => $manual && $normalizedStatus === 'paid' ? 'manual_review' : $normalizedStatus,
                'provider_status' => $providerStatus,
                'parent_payment_id' => $payload['parent_payment_id'] ?? $payment->parent_payment_id,
                'purchase_id' => $payload['purchase_id'] ?? $payment->purchase_id,
                'price_amount' => $payload['price_amount'] ?? $payment->price_amount,
                'price_currency' => $payload['price_currency'] ?? $payment->price_currency,
                'pay_amount' => $payload['pay_amount'] ?? $payment->pay_amount,
                'pay_currency' => $payload['pay_currency'] ?? $payment->pay_currency,
                'pay_address' => $payload['pay_address'] ?? $payment->pay_address,
                'payin_extra_id' => $payload['payin_extra_id'] ?? $payment->payin_extra_id,
                'actually_paid' => $payload['actually_paid'] ?? $payment->actually_paid,
                'outcome_amount' => $payload['outcome_amount'] ?? $payment->outcome_amount,
                'outcome_currency' => $payload['outcome_currency'] ?? $payment->outcome_currency,
                'requires_manual_review' => $manual,
                'last_webhook_payload' => SensitiveDataRedactor::redact($payload),
                'webhook_processed_at' => now(),
                'paid_at' => $normalizedStatus === 'paid' && !$manual ? ($payment->paid_at ?: now()) : $payment->paid_at,
                'failed_at' => in_array($normalizedStatus, ['failed', 'expired'], true) ? ($payment->failed_at ?: now()) : $payment->failed_at,
            ])->save();

            $orderState = $manual ? 'manual_review' : $normalizedStatus;
            if ($normalizedStatus === 'paid' && !$manual) $orderState = 'paid';
            $order->forceFill([
                'status_payment' => $orderState,
                'payment_state' => $orderState,
                'transaction_id' => $providerPaymentId ?: $order->transaction_id,
                'paid_at' => $normalizedStatus === 'paid' && !$manual ? ($order->paid_at ?: now()) : $order->paid_at,
            ])->save();

            $lockedEvent->update([
                'order_id' => $order->id,
                'payment_transaction_id' => $payment->id,
                'status' => $orderState,
                'processed_at' => now(),
            ]);

            if ($normalizedStatus === 'paid' && !$manual) return ['action' => 'complete', 'order_id' => $order->id, 'payment_id' => $payment->id];
            if (in_array($normalizedStatus, ['failed', 'expired'], true)) return ['action' => 'release', 'order_id' => $order->id];
            return ['action' => 'none', 'order_id' => $order->id];
        }, 3);

        if (($action['action'] ?? null) === 'complete') {
            $payment = PaymentTransaction::find($action['payment_id']);
            $order = $payment?->order;
            if ($order) $completePaidOrder->complete($order, $payment);
        } elseif (($action['action'] ?? null) === 'release') {
            $order = \App\Models\Order::find($action['order_id']);
            if ($order && $order->status_payment !== 'paid') {
                $inventory->releaseOrder($order, 'nowpayments_'.($payload['payment_status'] ?? 'failed'));
                app(\App\Services\Fulfillment\LocalSerialFulfillmentService::class)->releaseOrder($order, 'nowpayments_'.($payload['payment_status'] ?? 'failed'));
                $coupons->release($order);
            }
        }

        PaymentLogger::info('NOWPayments IPN processed', [
            'provider_payment_id' => $providerPaymentId,
            'order_number' => $providerOrderId,
            'action' => $action['action'] ?? 'none',
        ]);
        return response('OK', 200);
    }
}
