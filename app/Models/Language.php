<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
        'image_path',
        'is_active',
        'direction',
        'sort_order',
        'is_default',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::deleting(function ($language) {
            if ($language->code === 'ar' || $language->is_default) {
                throw new \Exception('لا يمكن حذف اللغة الأساسية أو الافتراضية.');
            }
        });
    }
}
