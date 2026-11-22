<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $provided = (string) $request->header('api-key');
        $expected = (string) config('services.internal_api.key', env('ERP_API_KEY', ''));
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            Log::warning('Unauthorized API key attempt', ['ip'=>$request->ip(),'request_id'=>$request->attributes->get('request_id')]);
            return response()->json(['status'=>'error','message'=>'Unauthorized'], 401);
        }
        return $next($request);
    }
}
