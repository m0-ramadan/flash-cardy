<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id','user_id','parent_id','name','email','content','is_approved',
        'moderated_by','moderated_at','spam_score',
    ];
    protected $casts = ['is_approved'=>'boolean','moderated_at'=>'datetime','spam_score'=>'decimal:2'];

    public function article(){ return $this->belongsTo(Article::class); }
    public function user(){ return $this->belongsTo(User::class); }
    public function moderator(){ return $this->belongsTo(Admin::class, 'moderated_by'); }
    public function parent(){ return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(){ return $this->hasMany(self::class, 'parent_id')->approved()->oldest(); }
    public function scopeApproved($query){ return $query->where('is_approved', true); }
}
