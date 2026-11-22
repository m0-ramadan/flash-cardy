<?php
// app/Models/OrderItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price_per_unit',
        'total_price',
        'is_sample',
        'note',
        'quantity_id',
        'image_design',
        'voucher_code',
        'provider_data', // Store like4app provider data
        'serial_codes', // Individual serial codes for this item
        'product_snapshot','customization_snapshot','provider_product_id','provider_reference',
        'provider_transaction_id','provider_status','fulfillment_source_snapshot','fulfillment_mode_snapshot','serial_status','fulfilled_at','failure_reason','refunded_quantity','design_file_id','pricing_snapshot',
    ];

    protected $casts = [
        'print_locations' => 'array',
        'embroider_locations' => 'array',
        'selected_options' => 'array',
        'price_per_unit' => 'decimal:4',
        'total_price' => 'decimal:4',
        'is_sample' => 'boolean',
        'provider_data' => 'array',
        'serial_codes' => 'array',
        'product_snapshot'=>'array','customization_snapshot'=>'array','pricing_snapshot'=>'array','fulfilled_at'=>'datetime','refunded_quantity'=>'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }



    public function getFormattedTotalAttribute()
    {
        return number_format($this->total_price, 2) . ' ج.م';
    }

    public function getProviderAttribute()
    {
        return $this->product && $this->product->external_id ? 'like4app' : 'internal';
    }

    public function assignedSerials(){ return $this->hasMany(ProductSerial::class); }
    public function like4AppPurchases(){ return $this->hasMany(Like4AppPurchase::class); }
    public function designFile(){ return $this->belongsTo(UploadedFile::class, 'design_file_id'); }
}
