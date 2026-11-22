<?php

namespace App\Services\Wallet;

use App\Models\Wallet\IdempotencyKey;
use App\Models\Wallet\LedgerEntry;
use App\Services\Security\FraudDetector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class AbstractWalletService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected FraudDetector $fraudDetector
    ) {}

    protected function credit($wallet, float $amount, string $type, string $description, array $metadata = [], array $ownerInfo = []): LedgerEntry
    {
        return $this->ledgerService->credit($wallet, $amount, $type, $description, $metadata, $ownerInfo);
    }

    protected function debit($wallet, float $amount, string $type, string $description, array $metadata = [], array $ownerInfo = []): LedgerEntry
    {
        return $this->ledgerService->debit($wallet, $amount, $type, $description, $metadata, $ownerInfo);
    }

    /**
     * A hold reduces available balance only. It does not debit the wallet balance until capture.
     */
    protected function hold(
        $wallet,
        float $amount,
        string $description,
        string $reference,
        array $metadata = [],
        array $ownerInfo = [],
        int $expiresInHours = 24
    ): LedgerEntry {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be greater than zero');

        return DB::transaction(function () use ($wallet, $amount, $description, $reference, $metadata, $ownerInfo, $expiresInHours) {
            $locked = $this->lockWallet($wallet);
            if ((float) $locked->available_balance < $amount) {
                throw new \RuntimeException('رصيد غير كافي للحجز');
            }

            $existing = LedgerEntry::where('reference', $reference)->lockForUpdate()->first();
            if ($existing) return $existing;

            $balance = (float) $locked->balance;
            $heldBefore = (float) $locked->held_balance;
            $entry = LedgerEntry::create([
                'wallet_type' => $locked->getWalletType(),
                'wallet_id' => $locked->id,
                'owner_type' => $ownerInfo['owner_type'] ?? LedgerEntry::OWNER_TYPE_SYSTEM,
                'owner_id' => $ownerInfo['owner_id'] ?? 0,
                'type' => LedgerEntry::TYPE_HOLD,
                'amount' => $amount,
                'balance_before' => $balance,
                'balance_after' => $balance,
                'available_balance_before' => $balance - $heldBefore,
                'available_balance_after' => $balance - ($heldBefore + $amount),
                'description' => $description,
                'status' => LedgerEntry::STATUS_PENDING,
                'reference' => $reference,
                'metadata' => array_merge($metadata, ['hold_reference' => $reference]),
                'expires_at' => now()->addHours($expiresInHours),
            ]);

            $locked->forceFill([
                'held_balance' => $heldBefore + $amount,
                'last_transaction_at' => now(),
                'version' => (int) $locked->version + 1,
            ])->save();

            return $entry;
        }, 3);
    }

    protected function releaseHold(string $reference, bool $complete = true, array $metadata = []): array
    {
        return DB::transaction(function () use ($reference, $complete, $metadata) {
            $hold = LedgerEntry::where('reference', $reference)
                ->where('type', LedgerEntry::TYPE_HOLD)
                ->lockForUpdate()
                ->first();

            if (!$hold) throw new \RuntimeException('الحجز غير موجود');
            if ($hold->status === LedgerEntry::STATUS_COMPLETED) {
                return ['action' => 'completed', 'hold_entry' => $hold];
            }
            if ($hold->status === LedgerEntry::STATUS_FAILED) {
                return ['action' => 'released', 'hold_entry' => $hold];
            }

            $wallet = $this->lockWallet($this->getWalletByType($hold->wallet_type, $hold->wallet_id));
            $heldBefore = (float) $wallet->held_balance;
            $wallet->forceFill([
                'held_balance' => max(0, $heldBefore - (float) $hold->amount),
                'last_transaction_at' => now(),
                'version' => (int) $wallet->version + 1,
            ])->save();

            if ($complete) {
                $paymentEntry = $this->debit(
                    $wallet,
                    (float) $hold->amount,
                    LedgerEntry::TYPE_PAYMENT,
                    'دفع: ' . $hold->description,
                    array_merge($metadata, ['hold_reference' => $reference, 'original_hold_id' => $hold->id]),
                    ['owner_type' => $hold->owner_type, 'owner_id' => $hold->owner_id]
                );
                $hold->markCompleted(['metadata' => array_merge($hold->metadata ?? [], ['completed_as' => 'payment'])]);
                return ['action' => 'completed', 'payment_entry' => $paymentEntry];
            }

            $hold->markFailed('released');
            return ['action' => 'released', 'hold_entry' => $hold];
        }, 3);
    }

    public function processWithIdempotency(
        string $key,
        array $requestData,
        callable $processCallback,
        ?string $ownerType = null,
        ?int $ownerId = null,
        int $ttl = 3600
    ) {
        ksort($requestData);
        $requestHash = hash('sha256', json_encode($requestData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $idempotency = IdempotencyKey::acquireLock($key, $requestHash, $ttl, $ownerType, $ownerId);

        if (!$idempotency) {
            throw new \RuntimeException('العملية قيد المعالجة حالياً');
        }

        if ($idempotency->status === IdempotencyKey::STATUS_COMPLETED) {
            if ($idempotency->resource_type && class_exists($idempotency->resource_type) && $idempotency->resource_id) {
                return $idempotency->resource_type::find($idempotency->resource_id);
            }
            return $idempotency->response_payload;
        }

        try {
            $result = DB::transaction(fn () => $processCallback(), 3);

            $payload = null;
            $resourceType = null;
            $resourceId = null;
            if ($result instanceof Model) {
                $resourceType = get_class($result);
                $resourceId = $result->getKey();
                $hashInput = $result->toArray();
            } else {
                $payload = is_array($result) ? $result : ['value' => $result];
                $hashInput = $payload;
            }

            $idempotency->completeWithResponse(
                hash('sha256', json_encode($hashInput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                $resourceType,
                $resourceId,
                $payload
            );

            return $result;
        } catch (\Throwable $e) {
            $idempotency->markFailed();
            Log::error('Idempotent wallet operation failed', [
                'key' => $key,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function generateReference(string $prefix): string
    {
        return $prefix . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(8));
    }

    abstract protected function lockWallet($wallet);
    abstract protected function getWalletByType(string $walletType, int $walletId);
}
