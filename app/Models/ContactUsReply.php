<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactUsReply extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contact_us_replies';
    protected $fillable = ['contact_us_id','user_id','admin_id','sender_id','message','sender_type'];
    protected $casts = ['contact_us_id'=>'integer','user_id'=>'integer','admin_id'=>'integer','sender_id'=>'integer'];

    public function contactUs(){ return $this->belongsTo(ContactUs::class, 'contact_us_id'); }
    public function user(){ return $this->belongsTo(User::class); }
    public function admin(){ return $this->belongsTo(Admin::class); }
    public function scopeAdmin($q){ return $q->where('sender_type','admin'); }
    public function scopeUser($q){ return $q->where('sender_type','user'); }

    public function getSenderNameAttribute(): string
    {
        if ($this->sender_type === 'admin') return $this->admin?->name ?? 'مسؤول';
        return $this->user?->name ?? 'مستخدم';
    }
}
