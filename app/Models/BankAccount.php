<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    protected $casts = ['is_active' => 'boolean'];

    protected $fillable = [
        'name',
        'image',
        'is_active',
        'type',
        'user_id', 'account_holder', 'account_identifier', 'currency',
    ];
}
