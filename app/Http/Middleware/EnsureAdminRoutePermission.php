<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRoutePermission
{
    private array $moduleMap = [
        'admins' => 'admins', 'roles' => 'roles', 'assign-roles' => 'roles', 'permissions' => 'permissions',
        'users' => 'users', 'products' => 'products', 'categories' => 'categories', 'orders' => 'orders', 'order' => 'orders',
        'banners' => 'banners', 'banner-items' => 'banners', 'coupons' => 'coupons', 'settings' => 'settings',
        'payment-methods' => 'payment_methods', 'payment_methods' => 'payment_methods', 'contact-us' => 'contact_us', 'contactus' => 'contact_us',
        'articles' => 'articles', 'article-categories' => 'article_categories', 'article-comments' => 'article_comments',
        'faqs' => 'faqs', 'languages' => 'languages', 'social-media' => 'social_media', 'static-pages' => 'static_pages',
        'ads' => 'ads', 'visitors' => 'visitors', 'subscribes' => 'subscriptions', 'subscriptions' => 'subscriptions',
        'errors' => 'system_logs', 'currencies' => 'currencies', 'countries' => 'countries', 'logistic-services' => 'logistic_services',
        'employees' => 'employees', 'managers' => 'managers', 'regions' => 'regions', 'reports' => 'reports',
        'inventory' => 'inventory', 'fulfillments' => 'fulfillments', 'refunds' => 'refunds', 'reviews' => 'reviews',
        'product-data' => 'products',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();
        if (!$admin || $admin->isSuperAdmin()) return $next($request);

        $path = trim($request->path(), '/');
        if ($path === 'admin/logout') return $next($request);

        $segments = explode('/', $path);
        if (($segments[0] ?? null) === 'admin') array_shift($segments);
        $root = $segments[0] ?? 'dashboard';
        $module = $this->moduleMap[$root] ?? ($root === '' ? 'dashboard' : str_replace('-', '_', $root));
        $action = $this->resolveAction($request);

        $permission = $module . '.' . $action;
        if (!$admin->can($permission) && !$admin->can($module . '.manage')) {
            abort(403, 'ليس لديك صلاحية لتنفيذ هذا الإجراء.');
        }
        return $next($request);
    }

    private function resolveAction(Request $request): string
    {
        $name = strtolower((string) optional($request->route())->getName());
        $method = strtoupper($request->method());

        if (str_contains($name, 'export') || str_contains($request->path(), 'export')) return 'export';
        if (str_contains($name, 'import') || str_contains($request->path(), 'import')) return 'import';
        if ($method === 'DELETE' || str_contains($name, 'delete') || str_contains($name, 'destroy')) return 'delete';
        if (str_contains($name, 'create') || str_contains($name, 'store')) return 'create';
        if (in_array($method, ['PUT','PATCH'], true) || str_contains($name, 'edit') || str_contains($name, 'update')) return 'edit';
        if ($method === 'GET' || in_array($method, ['HEAD','OPTIONS'], true)) return 'view';
        return 'manage';
    }
}
