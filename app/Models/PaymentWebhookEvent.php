<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = ['gateway','event_key','provider_transaction_id','order_id','payment_transaction_id','payload_hash','status','processed_at','payload'];
    protected $casts = ['payload'=>'array','processed_at'=>'datetime'];
}
