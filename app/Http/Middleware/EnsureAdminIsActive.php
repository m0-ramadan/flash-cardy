<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();
        if ($admin && $admin->is_active === false) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('admin.login.page')->withErrors(['email' => 'هذا الحساب الإداري غير نشط.']);
        }
        return $next($request);
    }
}
