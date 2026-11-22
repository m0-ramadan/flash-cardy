<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    protected $fillable = [
        'user_id','first_name','last_name','building','floor','apartment_number','address_details',
        'label','phone','city','area','type','latitude','longitude','is_default','address_role',
    ];
    protected $casts = ['is_default'=>'boolean','latitude'=>'decimal:8','longitude'=>'decimal:8'];
    public function user(){ return $this->belongsTo(User::class); }
}
