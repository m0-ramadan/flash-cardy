<?php

namespace App\Services\Wallet;

use App\Events\WalletEvent;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\Wallet\IdempotencyKey;
use App\Models\Wallet\LedgerEntry;
use App\Models\Wallet\UserWallet;
use App\Models\Wallet\WalletTransfer;
use App\Services\Payment\Wallet\PaymobService;
use App\Services\Security\FraudDetector;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

class UserWalletService extends AbstractWalletService
{
    public function __construct(
        LedgerService $ledgerService,
        FraudDetector $fraudDetector,
        private PaymobService $paymobService,
        private NotificationService $notifications
    ) {
        parent::__construct($ledgerService, $fraudDetector);
    }

    public function getBalance(User $user): array
    {
        $wallet = $user->userWallet ?? $user->createUserWallet();
        return [
            'wallet_id' => $wallet->id,
            'balance' => (float) $wallet->balance,
            'available_balance' => (float) $wallet->available_balance,
            'held_balance' => (float) $wallet->held_balance,
            'currency' => $wallet->currency,
            'status' => $wallet->status,
            'daily_limit' => (float) $wallet->daily_limit,
            'total_deposits_today' => (float) $wallet->total_deposits_today,
            'total_withdrawals_today' => (float) $wallet->total_withdrawals_today,
            'last_transaction_at' => $wallet->last_transaction_at,
        ];
    }

    public function initiateDeposit(User $user, float $amount, array $data = []): array
    {
        $wallet = $user->userWallet ?? $user->createUserWallet();
        $this->validateDepositAmount($amount);
        $orderId = $this->generateReference('DEP');
        $method = $data['payment_method'] ?? 'paymob';

        $pending = LedgerEntry::create([
            'wallet_type' => 'user',
            'wallet_id' => $wallet->id,
            'owner_type' => LedgerEntry::OWNER_TYPE_USER,
            'owner_id' => $user->id,
            'type' => LedgerEntry::TYPE_DEPOSIT_PENDING,
            'amount' => $amount,
            'balance_before' => $wallet->balance,
            'balance_after' => $wallet->balance,
            'available_balance_before' => $wallet->available_balance,
            'available_balance_after' => $wallet->available_balance,
            'description' => 'إيداع معلق - بانتظار التأكيد',
            'status' => LedgerEntry::STATUS_PENDING,
            'reference' => $orderId,
            'metadata' => array_merge($data, ['payment_method' => $method]),
            'expires_at' => now()->addHours(24),
        ]);

        $paymentData = $this->paymobService->createPaymentOrder([
            'user' => $user,
            'amount' => $amount,
            'wallet_currency' => $wallet->currency,
            'order_id' => $orderId,
            'callback_url' => '',
        ]);

        if (!($paymentData['success'] ?? false)) {
            $pending->markFailed($paymentData['error'] ?? 'Unable to create payment');
            throw new \RuntimeException($paymentData['error'] ?? 'Unable to create payment');
        }

        return [
            'success' => true,
            'payment_url' => $paymentData['payment_url'],
            'order_id' => $orderId,
            'entry_id' => $pending->id,
            'amount' => $amount,
            'currency' => $wallet->currency,
            'expires_at' => $paymentData['expires_at'] ?? null,
        ];
    }

    public function confirmDeposit(string $paymentTransactionId, array $details = []): ?LedgerEntry
    {
        $key = 'wallet_deposit_' . $paymentTransactionId;
        return $this->processWithIdempotency($key, $details + ['payment_transaction_id' => $paymentTransactionId], function () use ($paymentTransactionId, $details) {
            $pending = LedgerEntry::where('reference', $details['order_id'] ?? null)
                ->where('type', LedgerEntry::TYPE_DEPOSIT_PENDING)
                ->where('status', LedgerEntry::STATUS_PENDING)
                ->where('wallet_type', 'user')
                ->lockForUpdate()
                ->first();
            if (!$pending) throw new \RuntimeException('Pending transaction not found');
            if ($pending->isExpired()) throw new \RuntimeException('Pending transaction expired');

            $wallet = $this->lockWallet(UserWallet::findOrFail($pending->wallet_id));
            $chargedCurrency = $details['currency_charged'] ?? $wallet->currency;
            if (strtoupper($chargedCurrency) !== strtoupper($wallet->currency)) {
                throw new \RuntimeException('عملة عملية الإيداع لا تطابق عملة المحفظة');
            }

            if (!$this->fraudDetector->validateDeposit($wallet, (float) $pending->amount, 'paymob', $details)) {
                throw new \RuntimeException('فشل في التحقق من الأمان');
            }

            $entry = $this->credit(
                $wallet,
                (float) $pending->amount,
                LedgerEntry::TYPE_DEPOSIT,
                'إيداع ناجح',
                [
                    'payment_transaction_id' => $paymentTransactionId,
                    'order_id' => $details['order_id'] ?? null,
                    'reference' => 'DEP-PAID-' . $pending->id,
                ],
                ['owner_type' => LedgerEntry::OWNER_TYPE_USER, 'owner_id' => $pending->owner_id]
            );

            $pending->markCompleted([
                'payment_transaction_id' => $paymentTransactionId,
                'metadata' => array_merge($pending->metadata ?? [], ['confirmed_at' => now()->toIso8601String()]),
            ]);
            $wallet->refresh()->updateDailyTotals((float) $pending->amount, 'deposit');
            event(new WalletEvent($entry));
            return $entry;
        }, LedgerEntry::OWNER_TYPE_USER, $details['user_id'] ?? null);
    }

    public function withdraw(User $user, float $amount, array $data = []): LedgerEntry
    {
        $wallet = $user->userWallet ?? $user->createUserWallet();
        $bank = BankAccount::findOrFail($data['bank_account_id'] ?? 0);
        if ($bank->user_id && (int) $bank->user_id !== (int) $user->id) {
            throw new \RuntimeException('الحساب البنكي لا يخص المستخدم الحالي');
        }
        if ($bank->currency && strtoupper($bank->currency) !== strtoupper($wallet->currency)) {
            throw new \RuntimeException('عملة الحساب البنكي لا تطابق عملة المحفظة');
        }

        $this->validateWithdrawal($wallet, $amount);
        $idempotency = $data['idempotency_key'] ?? IdempotencyKey::generateKey('withdraw');

        return $this->processWithIdempotency($idempotency, [
            'user_id' => $user->id,
            'amount' => round($amount, 2),
            'bank_account_id' => $bank->id,
            'payment_identifier' => $data['payment_identifier'] ?? null,
        ], function () use ($wallet, $amount, $data, $user, $bank) {
            $entry = $this->debit(
                $wallet,
                $amount,
                LedgerEntry::TYPE_WITHDRAWAL,
                $data['description'] ?? 'سحب رصيد',
                [
                    'bank_account_id' => $bank->id,
                    'payment_identifier' => $data['payment_identifier'] ?? null,
                ],
                ['owner_type' => LedgerEntry::OWNER_TYPE_USER, 'owner_id' => $user->id]
            );
            $wallet->refresh()->updateDailyTotals($amount, 'withdrawal');
            event(new WalletEvent($entry));
            return $entry;
        }, LedgerEntry::OWNER_TYPE_USER, $user->id);
    }

    public function transfer(User $fromUser, User $toUser, float $amount, array $data = []): WalletTransfer
    {
        if ($fromUser->is($toUser)) throw new \InvalidArgumentException('لا يمكن التحويل لنفس الحساب');
        if ($amount <= 0) throw new \InvalidArgumentException('المبلغ يجب أن يكون أكبر من صفر');

        $fromWallet = $fromUser->userWallet ?? $fromUser->createUserWallet();
        $toWallet = $toUser->userWallet ?? $toUser->createUserWallet();
        if (strtoupper($fromWallet->currency) !== strtoupper($toWallet->currency)) {
            throw new \RuntimeException('لا يسمح بالتحويل المباشر بين محافظ بعملات مختلفة');
        }

        $idempotency = $data['idempotency_key'] ?? IdempotencyKey::generateKey('transfer');
        return $this->processWithIdempotency($idempotency, [
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'amount' => round($amount, 2),
            'currency' => $fromWallet->currency,
        ], function () use ($fromWallet, $toWallet, $amount, $fromUser, $toUser, $data, $idempotency) {
            // Lock in deterministic order to reduce deadlocks.
            $ids = [$fromWallet->id, $toWallet->id]; sort($ids);
            UserWallet::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $from = UserWallet::findOrFail($fromWallet->id);
            $to = UserWallet::findOrFail($toWallet->id);
            if ((float) $from->available_balance < $amount) throw new \RuntimeException('رصيد غير كافي');

            $reference = $this->generateReference('TRF');
            $debit = $this->debit($from, $amount, LedgerEntry::TYPE_TRANSFER_OUT, $data['description'] ?? 'تحويل أموال', [
                'transfer_reference' => $reference,
                'to_user_id' => $toUser->id,
            ], ['owner_type' => LedgerEntry::OWNER_TYPE_USER, 'owner_id' => $fromUser->id]);
            $credit = $this->credit($to, $amount, LedgerEntry::TYPE_TRANSFER_IN, $data['description'] ?? 'استلام تحويل', [
                'transfer_reference' => $reference,
                'from_user_id' => $fromUser->id,
            ], ['owner_type' => LedgerEntry::OWNER_TYPE_USER, 'owner_id' => $toUser->id]);

            $debit->update(['related_entry_id' => $credit->id]);
            $credit->update(['related_entry_id' => $debit->id]);
            $from->refresh()->updateDailyTotals($amount, 'transfer_out');

            $transfer = WalletTransfer::create([
                'reference' => $reference,
                'from_wallet_id' => $from->id,
                'to_wallet_id' => $to->id,
                'from_user_id' => $fromUser->id,
                'to_user_id' => $toUser->id,
                'amount' => $amount,
                'currency' => $from->currency,
                'status' => 'completed',
                'debit_entry_id' => $debit->id,
                'credit_entry_id' => $credit->id,
                'idempotency_key' => $idempotency,
                'metadata' => ['description' => $data['description'] ?? null],
            ]);

            event(new WalletEvent($debit));
            event(new WalletEvent($credit));
            return $transfer;
        }, LedgerEntry::OWNER_TYPE_USER, $fromUser->id);
    }

    public function creditRefund(User $user, float $amount, string $reference, array $metadata = []): LedgerEntry
    {
        $wallet = $user->userWallet ?? $user->createUserWallet();
        return $this->processWithIdempotency('wallet_refund_' . $reference, [
            'user_id' => $user->id,
            'amount' => round($amount, 2),
            'reference' => $reference,
            'currency' => $wallet->currency,
        ], function () use ($wallet, $amount, $reference, $metadata, $user) {
            $entry = $this->credit($wallet, $amount, LedgerEntry::TYPE_REFUND, 'استرداد إلى المحفظة', array_merge($metadata, [
                'reference' => 'WREF-' . $reference,
            ]), ['owner_type' => LedgerEntry::OWNER_TYPE_USER, 'owner_id' => $user->id]);
            event(new WalletEvent($entry));
            return $entry;
        }, LedgerEntry::OWNER_TYPE_USER, $user->id);
    }

    public function adjust(User $user, float $amount, string $direction, string $reason, int $adminId, string $idempotencyKey): LedgerEntry
    {
        if (!in_array($direction, ['credit', 'debit'], true)) throw new \InvalidArgumentException('Invalid adjustment direction');
        $wallet = $user->userWallet ?? $user->createUserWallet();
        $entry = $this->processWithIdempotency($idempotencyKey, [
            'user_id' => $user->id, 'amount' => round($amount, 2), 'direction' => $direction, 'admin_id' => $adminId,
        ], function () use ($wallet, $amount, $direction, $reason, $adminId, $user) {
            $metadata = ['admin_id' => $adminId, 'reason' => $reason];
            return $direction === 'credit'
                ? $this->credit($wallet, $amount, LedgerEntry::TYPE_ADJUSTMENT, $reason, $metadata, ['owner_type' => LedgerEntry::OWNER_TYPE_USER, 'owner_id' => $user->id])
                : $this->debit($wallet, $amount, LedgerEntry::TYPE_ADJUSTMENT, $reason, $metadata, ['owner_type' => LedgerEntry::OWNER_TYPE_USER, 'owner_id' => $user->id]);
        }, LedgerEntry::OWNER_TYPE_USER, $user->id);
        $this->notifications->user($user, 'تحديث المحفظة', ($direction === 'credit' ? 'تمت إضافة ' : 'تم خصم ') . number_format($amount, 2) . ' ' . $wallet->currency, 'wallet_transaction', ['entry_id'=>$entry->id,'direction'=>$direction,'amount'=>$amount]);
        return $entry;
    }

    public function holdForOrder(User $user, float $amount, string $orderId, array $data = []): LedgerEntry
    {
        $wallet = $user->userWallet ?? $user->createUserWallet();
        return $this->hold($wallet, $amount, $data['description'] ?? 'حجز للطلب #' . $orderId, 'ORDER-HOLD-' . $orderId,
            ['order_id' => $orderId], ['owner_type' => LedgerEntry::OWNER_TYPE_USER, 'owner_id' => $user->id], $data['expires_in_hours'] ?? 24);
    }

    public function getTransactionHistory(User $user, array $filters = [])
    {
        $query = LedgerEntry::where('owner_type', LedgerEntry::OWNER_TYPE_USER)->where('owner_id', $user->id)->latest();
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['start_date'])) $query->where('created_at', '>=', $filters['start_date']);
        if (!empty($filters['end_date'])) $query->where('created_at', '<=', $filters['end_date']);
        return $query->paginate(min(100, max(1, (int) ($filters['limit'] ?? 20))));
    }

    protected function lockWallet($wallet)
    {
        return UserWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
    }

    protected function getWalletByType(string $walletType, int $walletId)
    {
        if ($walletType !== 'user') throw new \RuntimeException('Unsupported wallet type');
        return UserWallet::findOrFail($walletId);
    }

    private function validateDepositAmount(float $amount): void
    {
        $min = (float) config('wallet.user.min_deposit', 10);
        $max = (float) config('wallet.user.max_deposit', 50000);
        if ($amount < $min || $amount > $max) throw new \InvalidArgumentException("المبلغ يجب أن يكون بين {$min} و {$max}");
    }

    private function validateWithdrawal(UserWallet $wallet, float $amount): void
    {
        if ((float) $wallet->available_balance < $amount) throw new \RuntimeException('رصيد غير كافي');
        if (!$wallet->canWithdrawToday($amount)) throw new \RuntimeException('تجاوز الحد اليومي للسحب');
        $min = (float) config('wallet.user.min_withdrawal', 50);
        if ($amount < $min) throw new \InvalidArgumentException("الحد الأدنى للسحب هو {$min}");
    }
}
