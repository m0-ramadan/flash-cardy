<?php

namespace App\Support;

class SensitiveDataRedactor
{
    private const KEYS = [
        'password','password_confirmation','token','access_token','refresh_token','id_token','authorization',
        'api_key','apikey','secret','client_secret','hmac','hash','securitycode','security_code','pin',
        'serial','serial_code','serialcode','serial_number','serialnumber','voucher','voucher_code','card_number','cvv',
        'pay_address','wallet_address','private_key','smtp_password',
    ];

    public static function redact(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        $out = [];
        foreach ($value as $key => $item) {
            $normalized = strtolower(str_replace(['-',' '], '_', (string) $key));
            if (in_array($normalized, self::KEYS, true) || str_contains($normalized, 'password') || str_contains($normalized, 'secret')) {
                $out[$key] = '[REDACTED]';
            } else {
                $out[$key] = is_array($item) ? self::redact($item) : $item;
            }
        }
        return $out;
    }
}
