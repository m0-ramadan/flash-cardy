<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAdminSessionIsFresh
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('admin')->check()) return $next($request);

        $timeout = max(5, (int) env('ADMIN_SESSION_LIFETIME', 120)) * 60;
        $last = (int) $request->session()->get('admin_last_activity', 0);

        if ($last > 0 && (time() - $last) > $timeout) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('admin.login.page')->withErrors(['email' => 'انتهت جلسة لوحة التحكم، سجل الدخول مرة أخرى.']);
        }

        $request->session()->put('admin_last_activity', time());
        return $next($request);
    }
}
