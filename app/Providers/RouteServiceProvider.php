<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('user-login', function (Request $request) {
            return [
                Limit::perMinute(5)->by('login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('login-email:'.mb_strtolower((string) $request->input('email'))),
            ];
        });

        RateLimiter::for('social-login', function (Request $request) {
            return Limit::perMinute(10)->by('social:'.$request->ip());
        });

        RateLimiter::for('otp-send', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));
            return [
                Limit::perMinutes(15, 5)->by('otp-send-ip:'.$request->ip()),
                Limit::perMinutes(15, 3)->by('otp-send-email:'.$email),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));
            return [
                Limit::perMinute(10)->by('otp-verify-ip:'.$request->ip()),
                Limit::perMinute(8)->by('otp-verify-email:'.$email),
            ];
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return [
                Limit::perMinute(5)->by('admin-login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('admin-login-email:'.mb_strtolower((string) $request->input('email'))),
            ];
        });

        RateLimiter::for('admin-password-reset', function (Request $request) {
            return Limit::perMinutes(15, 3)->by('admin-reset:'.$request->ip().':'.mb_strtolower((string) $request->input('email')));
        });

        RateLimiter::for('payment-webhook', fn (Request $request) => Limit::perMinute(120)->by('payment-webhook:'.$request->ip()));
        RateLimiter::for('contact-public', fn (Request $request) => Limit::perMinutes(10, 5)->by('contact:'.$request->ip()));
        RateLimiter::for('file-upload', fn (Request $request) => Limit::perMinute(15)->by('upload:'.($request->user()?->id ?: $request->ip())));
        RateLimiter::for('wallet-sensitive', fn (Request $request) => Limit::perMinute(10)->by('wallet:'.($request->user()?->id ?: $request->ip())));

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('web')
                ->group(base_path('routes/admin.php'));
        });
    }
}
