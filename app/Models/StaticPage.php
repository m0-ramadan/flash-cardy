<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaticPage extends Model
{
    use HasFactory;
    public $table = 'static_pages';
    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
    ];

    public function scopeActive($query){ return $query->where('status','active'); }

    protected static function booted(): void
    {
        static::saved(fn () => cache()->forget('static_page:' . request()->route('slug')));
        static::deleted(fn () => cache()->flush());
    }
}
