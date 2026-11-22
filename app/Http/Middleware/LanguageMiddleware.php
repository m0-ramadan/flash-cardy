<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LanguageMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $supportedLocales = ['ar', 'en'];

        if ($request->hasHeader('Accept-Language')) {
            $raw = (string) $request->header('Accept-Language');
            $locale = strtolower(substr(trim(strtok($raw, ',;-_')), 0, 2));
            if (in_array($locale, $supportedLocales, true)) {
                app()->setLocale($locale);
            } else {
                app()->setLocale(config('app.locale', 'ar'));
            }
        } elseif (session()->has('locale')) {
            $sLocale = (string) session('locale');
            if (in_array($sLocale, $supportedLocales, true)) {
                app()->setLocale($sLocale);
            }
        }
        
        return $next($request);
    }
}