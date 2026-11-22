<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    public const TYPE_RESERVE = 'reserve';
    public const TYPE_RELEASE = 'release';
    public const TYPE_SALE = 'sale';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'product_id', 'order_id', 'order_item_id', 'admin_id', 'type', 'quantity',
        'stock_before', 'stock_after', 'reserved_before', 'reserved_after',
        'reference', 'reason', 'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
        'reserved_before' => 'integer',
        'reserved_after' => 'integer',
        'metadata' => 'array',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
}
