<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSerial extends Model
{
    public const AVAILABLE = 'available';
    public const ASSIGNED = 'assigned';
    public const DELIVERED = 'delivered';
    public const EXPIRED = 'expired';
    public const DISABLED = 'disabled';

    protected $fillable = [
        'product_id','code_hash','code','pin','expires_at','status','order_id','order_item_id',
        'reserved_at','assigned_at','delivered_at','disabled_at','source','batch_reference','notes','created_by',
    ];

    protected $hidden = ['code','pin','code_hash'];

    protected $casts = [
        'code' => 'encrypted',
        'pin' => 'encrypted',
        'expires_at' => 'datetime',
        'reserved_at' => 'datetime',
        'assigned_at' => 'datetime',
        'delivered_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function creator() { return $this->belongsTo(Admin::class, 'created_by'); }

    public function scopeUsable($query)
    {
        return $query->where('status', self::AVAILABLE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function getMaskedCodeAttribute(): string
    {
        $value = (string) $this->code;
        if (mb_strlen($value) <= 6) return str_repeat('*', max(0, mb_strlen($value)-2)).mb_substr($value, -2);
        return mb_substr($value, 0, 2).str_repeat('*', max(4, mb_strlen($value)-6)).mb_substr($value, -4);
    }

    public static function hashCode(string $code): string
    {
        return hash('sha256', trim($code));
    }
}
