<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CouponReservation extends Model { protected $fillable=['coupon_id','order_id','user_id','status','discount_amount','expires_at','consumed_at','released_at']; protected $casts=['discount_amount'=>'decimal:4','expires_at'=>'datetime','consumed_at'=>'datetime','released_at'=>'datetime']; public function coupon(){return $this->belongsTo(Coupon::class);} public function order(){return $this->belongsTo(Order::class);} }
