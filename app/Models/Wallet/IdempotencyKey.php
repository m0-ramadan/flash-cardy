<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IdempotencyKey extends Model
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'key', 'request_hash', 'response_hash', 'response_payload', 'status', 'processed_at',
        'expires_at', 'resource_type', 'resource_id', 'owner_type', 'owner_id',
    ];

    protected $casts = [
        'response_payload' => 'array',
        'expires_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public static function generateKey(string $prefix = 'idemp'): string
    {
        return $prefix . '_' . Str::uuid();
    }

    public static function acquireLock(
        string $key,
        string $requestHash,
        int $ttl,
        ?string $ownerType = null,
        ?int $ownerId = null
    ): ?self {
        return DB::transaction(function () use ($key, $requestHash, $ttl, $ownerType, $ownerId) {
            $existing = self::where('key', $key)->lockForUpdate()->first();

            if ($existing) {
                if (!hash_equals((string) $existing->request_hash, $requestHash)) {
                    throw new \RuntimeException('تم استخدام مفتاح Idempotency نفسه لطلب مختلف');
                }

                if ($existing->status === self::STATUS_COMPLETED) {
                    return $existing;
                }

                if ($existing->status === self::STATUS_PROCESSING && $existing->expires_at?->isFuture()) {
                    return null;
                }

                $existing->forceFill([
                    'status' => self::STATUS_PROCESSING,
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'expires_at' => now()->addSeconds($ttl),
                ])->save();

                return $existing;
            }

            return self::create([
                'key' => $key,
                'request_hash' => $requestHash,
                'status' => self::STATUS_PROCESSING,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'expires_at' => now()->addSeconds($ttl),
            ]);
        }, 3);
    }

    public function completeWithResponse(
        string $responseHash,
        ?string $resourceType = null,
        $resourceId = null,
        ?array $responsePayload = null
    ): bool {
        return $this->update([
            'response_hash' => $responseHash,
            'response_payload' => $responsePayload,
            'status' => self::STATUS_COMPLETED,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'processed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function markFailed(): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'processed_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->expires_at?->isFuture();
    }

    public static function cleanup(int $hours = 24): int
    {
        return self::where('expires_at', '<', now()->subHours($hours))->delete();
    }
}
