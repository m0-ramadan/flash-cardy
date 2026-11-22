<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadedFile extends Model
{
    protected $fillable = [
        'uuid','user_id','admin_id','disk','path','original_name','stored_name','extension','mime_type','size','sha256','purpose','is_public','metadata',
    ];
    protected $casts = ['is_public'=>'boolean','metadata'=>'array','size'=>'integer'];
    public function getRouteKeyName(): string { return 'uuid'; }
    public function user(){ return $this->belongsTo(User::class); }
    public function admin(){ return $this->belongsTo(Admin::class); }
}
