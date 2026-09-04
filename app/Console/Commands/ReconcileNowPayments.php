<?php

namespace App\Console\Commands;

use App\Models\PaymentTransaction;
use App\Services\Payment\NowPaymentsService;
use App\Support\PaymentLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileNowPayments extends Command
{
    protected $signature = 'payments:reconcile-nowpayments {--limit=25}';

    protected $description = 'Reconcile non-final NOWPayments payment transactions.';

    public function handle(NowPaymentsService $nowPayments): int
    {
        $limit = max(1, min((int) $this->option('limit'), 100));

        $payments = PaymentTransaction::query()
            ->where('gateway', 'nowpayments')
            ->whereNotNull('provider_payment_id')
            ->whereIn('status', ['waiting', 'confirming', 'confirmed', 'sending', 'unknown'])
            ->oldest()
            ->limit($limit)
            ->get();

        foreach ($payments as $payment) {
            try {
                $provider = $nowPayments->getPaymentStatus($payment->provider_payment_id);
                $providerStatus = (string) ($provider['payment_status'] ?? 'unknown');
                $normalized = $nowPayments->normalizeStatus($providerStatus);

                DB::transaction(function () use ($payment, $provider, $providerStatus, $normalized) {
                    $locked = PaymentTransaction::whereKey($payment->id)->lockForUpdate()->first();

                    if (! $locked) {
                        return;
                    }

                    $locked->update([
                        'status' => $normalized,
                        'provider_status' => $providerStatus,
                        'actually_paid' => $provider['actually_paid'] ?? $locked->actually_paid,
                        'provider_response' => $provider,
                        'failed_at' => in_array($normalized, ['failed', 'expired'], true) ? now() : $locked->failed_at,
                    ]);
                });
            } catch (\Throwable $e) {
                PaymentLogger::warning('NOWPayments reconciliation failed', [
                    'gateway' => 'nowpayments',
                    'local_payment_id' => $payment->id,
                    'provider_payment_id' => $payment->provider_payment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Reconciled {$payments->count()} NOWPayments payments.");

        return self::SUCCESS;
    }
}
