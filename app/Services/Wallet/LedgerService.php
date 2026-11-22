<?php

namespace App\Services\Wallet;

use App\Models\Wallet\AbstractWallet;
use App\Models\Wallet\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerService
{
    public function credit(
        AbstractWallet $wallet,
        float $amount,
        string $type,
        string $description,
        array $metadata = [],
        array $ownerInfo = []
    ): LedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }

        return DB::transaction(function () use ($wallet, $amount, $type, $description, $metadata, $ownerInfo) {
            $locked = $wallet->newQuery()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $before = (float) $locked->balance;
            $availableBefore = (float) $locked->available_balance;
            $after = round($before + $amount, 2);

            $entry = LedgerEntry::create([
                'wallet_type' => $locked->getWalletType(),
                'wallet_id' => $locked->id,
                'owner_type' => $ownerInfo['owner_type'] ?? LedgerEntry::OWNER_TYPE_SYSTEM,
                'owner_id' => $ownerInfo['owner_id'] ?? 0,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'available_balance_before' => $availableBefore,
                'available_balance_after' => $availableBefore + $amount,
                'description' => $description,
                'status' => LedgerEntry::STATUS_COMPLETED,
                'reference' => $metadata['reference'] ?? $this->generateReference($type),
                'metadata' => $metadata,
                'processed_at' => now(),
            ]);

            $locked->forceFill([
                'balance' => $after,
                'last_transaction_at' => now(),
                'version' => (int) $locked->version + 1,
            ])->save();

            return $entry;
        }, 3);
    }

    public function debit(
        AbstractWallet $wallet,
        float $amount,
        string $type,
        string $description,
        array $metadata = [],
        array $ownerInfo = []
    ): LedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }

        return DB::transaction(function () use ($wallet, $amount, $type, $description, $metadata, $ownerInfo) {
            $locked = $wallet->newQuery()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $before = (float) $locked->balance;
            $availableBefore = (float) $locked->available_balance;

            if ($availableBefore < $amount) {
                throw new \RuntimeException('رصيد غير كافي');
            }

            $after = round($before - $amount, 2);
            if ($after < 0) {
                throw new \RuntimeException('الرصيد لا يمكن أن يصبح سالباً');
            }

            $entry = LedgerEntry::create([
                'wallet_type' => $locked->getWalletType(),
                'wallet_id' => $locked->id,
                'owner_type' => $ownerInfo['owner_type'] ?? LedgerEntry::OWNER_TYPE_SYSTEM,
                'owner_id' => $ownerInfo['owner_id'] ?? 0,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'available_balance_before' => $availableBefore,
                'available_balance_after' => $availableBefore - $amount,
                'description' => $description,
                'status' => LedgerEntry::STATUS_COMPLETED,
                'reference' => $metadata['reference'] ?? $this->generateReference($type),
                'metadata' => $metadata,
                'processed_at' => now(),
            ]);

            $locked->forceFill([
                'balance' => $after,
                'last_transaction_at' => now(),
                'version' => (int) $locked->version + 1,
            ])->save();

            return $entry;
        }, 3);
    }

    public function getLedgerSummary(AbstractWallet $wallet, array $filters = []): array
    {
        $query = $wallet->ledgerEntries()->where('status', LedgerEntry::STATUS_COMPLETED);
        if (!empty($filters['start_date'])) $query->where('created_at', '>=', $filters['start_date']);
        if (!empty($filters['end_date'])) $query->where('created_at', '<=', $filters['end_date']);

        $summary = (clone $query)
            ->select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('type')
            ->get();

        return [
            'summary' => $summary,
            'wallet_balance' => (float) $wallet->balance,
            'calculated_balance' => $wallet->calculateLedgerBalance(),
            'discrepancy' => abs((float) $wallet->balance - $wallet->calculateLedgerBalance()),
        ];
    }

    private function generateReference(string $type): string
    {
        $prefixes = [
            LedgerEntry::TYPE_DEPOSIT => 'DEP',
            LedgerEntry::TYPE_WITHDRAWAL => 'WTH',
            LedgerEntry::TYPE_CASHOUT => 'CASH',
            LedgerEntry::TYPE_TRANSFER_IN => 'TIN',
            LedgerEntry::TYPE_TRANSFER_OUT => 'TOUT',
            LedgerEntry::TYPE_EARNING => 'ERN',
            LedgerEntry::TYPE_COMMISSION => 'COM',
            LedgerEntry::TYPE_PAYMENT => 'PAY',
            LedgerEntry::TYPE_HOLD => 'HLD',
            LedgerEntry::TYPE_REFUND => 'REF',
            LedgerEntry::TYPE_FEE => 'FEE',
            LedgerEntry::TYPE_RELEASE => 'REL',
            LedgerEntry::TYPE_PAYOUT => 'POUT',
            LedgerEntry::TYPE_ADJUSTMENT => 'ADJ',
        ];
        return ($prefixes[$type] ?? 'LED') . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(8));
    }
}
