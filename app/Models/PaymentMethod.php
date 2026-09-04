<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'icon',
        'is_active',
        'is_payment',
        'is_wallet',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_payment' => 'boolean',
        'is_wallet' => 'boolean',
    ];

    protected $appends = [
        'icon_url',
        'icon_type',
    ];

    public function hasFontAwesomeIcon(): bool
    {
        if (! $this->icon) {
            return false;
        }

        return preg_match(
            '/^(fas|far|fab|fal|fad|fa-solid|fa-regular|fa-brands)\s+fa-[a-z0-9-]+$/i',
            trim($this->icon)
        ) === 1;
    }

    public function hasImageIcon(): bool
    {
        return filled($this->icon) && ! $this->hasFontAwesomeIcon();
    }

    public function getIconTypeAttribute(): ?string
    {
        if (! $this->icon) {
            return null;
        }

        return $this->hasFontAwesomeIcon() ? 'fontawesome' : 'image';
    }

    public function getIconUrlAttribute()
    {
        if (! $this->hasImageIcon()) {
            return null;
        }

        if (filter_var($this->icon, FILTER_VALIDATE_URL)) {
            return $this->icon;
        }

        return Storage::disk('public')->url('payment-methods/'.$this->icon);
    }

    /**
     * Scope active payment methods
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope payment methods only (not other methods)
     */
    public function scopePaymentOnly($query)
    {
        return $query->where('is_payment', true);
    }

    /**
     * Scope other methods (not payment methods)
     */
    public function scopeOtherOnly($query)
    {
        return $query->where('is_payment', false);
    }

    /**
     * Generate a unique key for the payment method
     */
    public static function generateUniqueKey($name)
    {
        $key = str_slug($name, '-', 'ar');
        $counter = 1;
        $originalKey = $key;

        while (self::where('key', $key)->exists()) {
            $key = $originalKey.'-'.$counter;
            $counter++;
        }

        return $key;
    }
}
