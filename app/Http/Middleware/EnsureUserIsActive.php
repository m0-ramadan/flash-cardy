<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ($user->is_active === false || $user->deactivated_at !== null)) {
            $user->currentAccessToken()?->delete();
            return response()->json(['status' => false, 'message' => 'الحساب غير متاح حاليًا'], 403);
        }
        return $next($request);
    }
}
