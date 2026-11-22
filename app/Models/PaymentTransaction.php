<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_method_id',
        'gateway',
        'provider_payment_id',
        'parent_payment_id',
        'purchase_id',
        'status',
        'provider_status',
        'original_amount',
        'original_currency',
        'exchange_rate',
        'price_amount',
        'price_currency',
        'pay_amount',
        'actually_paid',
        'pay_currency',
        'pay_address',
        'payin_extra_id',
        'outcome_amount',
        'outcome_currency',
        'is_fixed_rate',
        'is_fee_paid_by_user',
        'requires_manual_review',
        'failure_reason',
        'initial_payload',
        'provider_response',
        'last_webhook_payload',
        'metadata',
        'expires_at',
        'paid_at',
        'failed_at',
        'provider_intention_id','provider_transaction_id','idempotency_key','webhook_processed_at',
    ];

    protected $casts = [
        'original_amount' => 'decimal:4',
        'exchange_rate' => 'decimal:10',
        'price_amount' => 'decimal:4',
        'pay_amount' => 'decimal:20',
        'actually_paid' => 'decimal:20',
        'outcome_amount' => 'decimal:20',
        'is_fixed_rate' => 'boolean',
        'is_fee_paid_by_user' => 'boolean',
        'requires_manual_review' => 'boolean',
        'initial_payload' => 'array',
        'provider_response' => 'array',
        'last_webhook_payload' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'webhook_processed_at'=>'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
