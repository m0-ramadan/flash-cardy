<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OtpVerification extends Model
{
    protected $fillable = ['email', 'otp', 'expires_at', 'used_at', 'attempts', 'verified_at', 'purpose'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function isExpired(): bool
    {
        return Carbon::now()->gt($this->expires_at);
    }

    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }
}