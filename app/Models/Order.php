<?php

// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'cart_id',
        'session_id',
        'order_number',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'payment_method',
        'transaction_id',
        'notes',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shipped_at',
        'delivered_at',
        'status_payment',
        'paid_at',
        'image',
        'provider', // internal, like4app
        'provider_order_id', // like4app order ID
        'provider_response', // full response from provider
        'serial_codes', // JSON array of serial codes
        'shipping_amount','coupon_id','address_id','shipping_address','shipping_address_snapshot','billing_address_snapshot','payment_method_id','currency','order_state','payment_state','fulfillment_status','refund_status','refunded_amount','inventory_released_at','inventory_committed_at','fulfilled_at',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'paid_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'provider_response' => 'array',
        'serial_codes' => 'array',
        'shipping_address_snapshot'=>'array','billing_address_snapshot'=>'array','refunded_amount'=>'decimal:4','inventory_released_at'=>'datetime','inventory_committed_at'=>'datetime','fulfilled_at'=>'datetime',
    ];

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/'.$this->image);
        }

        return null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function latestPaymentTransaction()
    {
        return $this->hasOne(PaymentTransaction::class)->latestOfMany();
    }

    public function logs()
    {
        return $this->hasMany(OrderLog::class);
    }

    public function getFormattedOrderNumberAttribute()
    {
        return $this->order_number;
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'pending' => 'قيد الانتظار',
            'processing' => 'تحت المعالجة',
            'completed' => 'تم التسليم',
            'cancelled' => 'ملغي',
            'refunded' => 'تم الاسترداد',
            default => 'غير معروف',
        };
    }

    public function getProviderLabelAttribute()
    {
        return match ($this->provider) {
            'like4app' => 'لايك كارد',
            'internal' => 'داخلي',
            default => 'غير معروف',
        };
    }

    // protected static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($order) {
    //         if (!$order->order_number) {
    //             $order->order_number = 'ORD-' . date('Y') . '-' . str_pad(static::max('id') + 1, 6, '0', STR_PAD_LEFT);
    //         }
    //     });
    // }
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (! $order->order_number) {
                $year = date('Y');

                $lastOrder = static::withTrashed()
                    ->where('order_number', 'like', 'ORD-'.$year.'-%')
                    ->orderByDesc('id')
                    ->first();

                $lastNumber = 0;

                if ($lastOrder && preg_match('/ORD-\d{4}-(\d+)/', $lastOrder->order_number, $matches)) {
                    $lastNumber = (int) $matches[1];
                }

                $newNumber = $lastNumber + 1;

                $order->order_number = 'ORD-'.$year.'-'.str_pad($newNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function refunds(){ return $this->hasMany(Refund::class); }
    public function stockReservations(){ return $this->hasMany(StockReservation::class); }
}
