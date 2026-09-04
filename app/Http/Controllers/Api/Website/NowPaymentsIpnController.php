<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Payment\CompletePaidOrderService;
use App\Services\Payment\NowPaymentsService;
use App\Support\PaymentLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;

class NowPaymentsIpnController extends Controller
{
    public function __invoke(
        Request $request,
        NowPaymentsService $nowPayments,
        CompletePaidOrderService $completePaidOrder
    ) {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response('Malformed JSON', 400);
        }

        if (! is_array($payload)) {
            return response('Malformed JSON', 400);
        }

        $signature = $request->header('x-nowpayments-sig');

        if (! $signature) {
            return response('Missing signature', 401);
        }

        if (! $nowPayments->verifyIpnSignature($payload, $signature)) {
            return response('Invalid signature', 401);
        }

        $providerPaymentId = (string) ($payload['payment_id'] ?? '');

        $providerOrderId = (string) ($payload['order_id'] ?? '');

        if ($providerPaymentId === '' && $providerOrderId === '') {
            PaymentLogger::warning('NOWPayments IPN missing payment_id', [
                'gateway' => 'nowpayments',
            ]);

            return response('OK', 200);
        }

        DB::transaction(function () use ($payload, $providerPaymentId, $providerOrderId, $nowPayments, $completePaidOrder) {
            $payment = null;

            if ($providerPaymentId !== '') {
                $payment = PaymentTransaction::where('provider_payment_id', $providerPaymentId)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $payment && $providerOrderId !== '') {
                $payment = PaymentTransaction::query()
                    ->where('gateway', 'nowpayments')
                    ->whereHas('order', fn ($query) => $query->where('order_number', $providerOrderId))
                    ->latest()
                    ->lockForUpdate()
                    ->first();
            }

            if (! $payment) {
                PaymentLogger::warning('NOWPayments IPN for unknown payment', [
                    'gateway' => 'nowpayments',
                    'provider_payment_id' => $providerPaymentId,
                    'order_id' => $providerOrderId,
                    'provider_status' => $payload['payment_status'] ?? null,
                ]);

                return;
            }

            $order = $payment->order()->lockForUpdate()->first();
            $providerStatus = (string) ($payload['payment_status'] ?? 'unknown');
            $normalizedStatus = $nowPayments->normalizeStatus($providerStatus);
            $requiresManualReview = $payment->requires_manual_review;

            if (($payload['parent_payment_id'] ?? null) !== null) {
                $requiresManualReview = true;
            }

            if (($payload['order_id'] ?? null) !== $order?->order_number) {
                $requiresManualReview = true;
            }

            if (
                $payment->price_currency
                && isset($payload['price_currency'])
                && strtolower((string) $payload['price_currency']) !== strtolower((string) $payment->price_currency)
            ) {
                $requiresManualReview = true;
            }

            if (
                $payment->pay_currency
                && isset($payload['pay_currency'])
                && strtolower((string) $payload['pay_currency']) !== strtolower((string) $payment->pay_currency)
            ) {
                $requiresManualReview = true;
            }

            if (isset($payload['price_amount']) && bccomp((string) $payload['price_amount'], (string) $payment->price_amount, 4) !== 0) {
                $requiresManualReview = true;
            }

            $actuallyPaid = (string) ($payload['actually_paid'] ?? $payment->actually_paid ?? '0');
            $payAmount = (string) ($payload['pay_amount'] ?? $payment->pay_amount ?? '0');

            if ($normalizedStatus === 'paid' && bccomp($actuallyPaid, $payAmount, 20) < 0) {
                $normalizedStatus = 'partially_paid';
                $requiresManualReview = true;
            }

            $payment->update([
                'provider_payment_id' => $providerPaymentId !== '' ? $providerPaymentId : $payment->provider_payment_id,
                'status' => $normalizedStatus,
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
                'requires_manual_review' => $requiresManualReview,
                'last_webhook_payload' => $payload,
                'paid_at' => $normalizedStatus === 'paid' && ! $requiresManualReview ? now() : $payment->paid_at,
                'failed_at' => in_array($normalizedStatus, ['failed', 'expired'], true) ? now() : $payment->failed_at,
            ]);

            if (! $order) {
                return;
            }

            if ($normalizedStatus === 'paid' && ! $requiresManualReview) {
                $completePaidOrder->complete($order);
            } elseif ($order->status_payment !== 'paid') {
                $order->update(['status_payment' => $normalizedStatus]);
            }

            PaymentLogger::info('NOWPayments IPN processed', [
                'gateway' => 'nowpayments',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'provider_payment_id' => $providerPaymentId,
                'provider_status' => $providerStatus,
                'normalized_status' => $normalizedStatus,
                'requires_manual_review' => $requiresManualReview,
            ]);
        });

        return response('OK', 200);
    }
}
