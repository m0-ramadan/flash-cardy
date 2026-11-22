<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'rating', 'comment', 'is_approved',
        'is_verified_purchase', 'moderated_by', 'moderated_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
        'is_verified_purchase' => 'boolean',
        'moderated_at' => 'datetime',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function moderator() { return $this->belongsTo(Admin::class, 'moderated_by'); }
    public function scopeApproved($query) { return $query->where('is_approved', true); }
}
