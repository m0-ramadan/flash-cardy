<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use App\Support\SensitiveDataRedactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuditAdminMutation
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (!in_array($request->method(), ['POST','PUT','PATCH','DELETE'], true) || !auth('admin')->check()) return $response;

        try {
            if (Schema::hasTable('admin_audit_logs')) {
                $safe = SensitiveDataRedactor::redact($request->except(['_token']));
                AdminAuditLog::create([
                    'admin_id' => auth('admin')->id(),
                    'route_name' => optional($request->route())->getName(),
                    'method' => $request->method(),
                    'path' => '/'.$request->path(),
                    'action' => optional($request->route())->getActionMethod(),
                    'response_status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                    'request_summary' => $safe,
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                    'request_id' => $request->headers->get('X-Request-Id'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Admin audit logging failed', ['error_class' => $e::class]);
        }
        return $response;
    }
}
