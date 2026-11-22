<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PENDING = 'refund_pending';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'order_id', 'payment_transaction_id', 'requested_by_admin_id', 'gateway', 'type',
        'status', 'amount', 'currency', 'reason', 'provider_reference', 'provider_response',
        'failure_reason', 'idempotency_key', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'provider_response' => 'array',
        'processed_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function paymentTransaction() { return $this->belongsTo(PaymentTransaction::class); }
    public function requestedByAdmin() { return $this->belongsTo(Admin::class, 'requested_by_admin_id'); }
}
