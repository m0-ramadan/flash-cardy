<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'order_id', 'order_item_id', 'product_id', 'quantity', 'status', 'reference',
        'reserved_at', 'committed_at', 'released_at', 'release_reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_at' => 'datetime',
        'committed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
