<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class PaymentLogger
{
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    private static function log(string $level, string $message, array $context): void
    {
        if (config('logging.channels.payment')) {
            Log::channel('payment')->log($level, $message, $context);

            return;
        }

        Log::log($level, $message, $context);
    }
}
