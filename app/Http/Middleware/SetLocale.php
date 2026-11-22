<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // الحصول على اللغة من الهيدر
        $locale = $request->header('Accept-Language', config('app.locale'));
        
        // تنظيف وتأكيد اللغة
        $locale = $this->sanitizeLocale($locale);
        
        // تعيين اللغة للتطبيق
        app()->setLocale($locale);
        
        return $next($request);
    }

    /**
     * تنظيف وتأكيد صحة اللغة
     */
    private function sanitizeLocale($locale): string
    {
        $supportedLocales = ['ar', 'en', 'fr', 'es'];
        
        $first = strtok((string)$locale, ',;');
        $clean = strtolower(substr(trim(strtok($first, '-_')), 0, 2));
        
        if (in_array($clean, $supportedLocales, true)) {
            return $clean;
        }
        
        return config('app.fallback_locale', 'ar');
    }
}