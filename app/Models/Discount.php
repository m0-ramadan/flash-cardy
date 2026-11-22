<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = [
        'product_id',
        'discount_value',
        'discount_type',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
