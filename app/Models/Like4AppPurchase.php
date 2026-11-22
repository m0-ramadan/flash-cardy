<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Like4AppPurchase extends Model
{
    protected $table = 'like4app_purchases';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'order_id', 'order_item_id', 'product_id', 'provider_product_id', 'stable_reference',
        'status', 'unit_index', 'requested_quantity', 'fulfilled_quantity', 'provider_transaction_id',
        'serial_codes', 'voucher_codes', 'provider_response', 'failure_reason', 'attempts',
        'last_attempt_at', 'fulfilled_at',
    ];

    protected $casts = [
        'unit_index' => 'integer',
        'requested_quantity' => 'integer',
        'fulfilled_quantity' => 'integer',
        'serial_codes' => 'array',
        'voucher_codes' => 'array',
        'provider_response' => 'array',
        'last_attempt_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
