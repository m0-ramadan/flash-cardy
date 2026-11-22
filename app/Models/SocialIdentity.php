<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialIdentity extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'provider_user_id', 'email', 'metadata', 'last_verified_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_verified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
