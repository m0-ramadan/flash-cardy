<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $modules = [
        'dashboard', 'users', 'admins', 'roles', 'permissions', 'products', 'categories', 'orders',
        'banners', 'coupons', 'settings', 'reports', 'payment_methods', 'contact_us', 'about',
        'articles', 'article_categories', 'article_comments', 'faqs', 'languages', 'social_media',
        'static_pages', 'ads', 'visitors', 'subscriptions', 'system_logs', 'currencies', 'countries',
        'logistic_services', 'employees', 'managers', 'regions',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        foreach ($this->modules as $module) {
            foreach (['view', 'create', 'edit', 'delete', 'manage', 'export', 'import'] as $action) {
                // Read-only modules still receive the full vocabulary to keep route policy predictable.
                DB::table('permissions')->updateOrInsert(
                    ['name' => "{$module}.{$action}", 'guard_name' => 'admin'],
                    [
                        'display_name' => "{$module} {$action}",
                        'description' => "{$action} {$module}",
                        'module' => $module,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        $roles = [
            'product_manager' => ['display_name' => 'مدير المنتجات', 'description' => 'إدارة المنتجات والأقسام', 'modules' => ['products', 'categories']],
            'orders_manager' => ['display_name' => 'مدير الطلبات', 'description' => 'إدارة الطلبات والعملاء', 'modules' => ['orders', 'users']],
            'finance' => ['display_name' => 'المالية', 'description' => 'المدفوعات والتقارير', 'modules' => ['payment_methods', 'reports', 'orders']],
            'support' => ['display_name' => 'الدعم', 'description' => 'تذاكر ودعم العملاء', 'modules' => ['contact_us', 'users']],
            'content_manager' => ['display_name' => 'مدير المحتوى', 'description' => 'المقالات والمحتوى والبانرات', 'modules' => ['articles', 'article_categories', 'article_comments', 'faqs', 'banners', 'ads', 'static_pages', 'languages', 'social_media']],
            'settings_administrator' => ['display_name' => 'مدير الإعدادات', 'description' => 'إعدادات النظام فقط', 'modules' => ['settings', 'currencies']],
        ];

        foreach ($roles as $name => $definition) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'admin'],
                [
                    'display_name' => $definition['display_name'],
                    'description' => $definition['description'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $roleId = DB::table('roles')->where('name', $name)->where('guard_name', 'admin')->value('id');
            $permissionIds = DB::table('permissions')
                ->where('guard_name', 'admin')
                ->where(function ($query) use ($definition) {
                    foreach ($definition['modules'] as $module) {
                        $query->orWhere('module', $module);
                    }
                })
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        // Preserve the historic broad admin role now that route enforcement becomes real.
        $adminRoleId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'admin')->value('id');
        if ($adminRoleId) {
            foreach (DB::table('permissions')->where('guard_name', 'admin')->pluck('id') as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $adminRoleId]);
            }
        }

        $viewerRoleId = DB::table('roles')->where('name', 'viewer')->where('guard_name', 'admin')->value('id');
        if ($viewerRoleId) {
            foreach (DB::table('permissions')->where('guard_name', 'admin')->where('name', 'like', '%.view')->pluck('id') as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $viewerRoleId]);
            }
        }

        $editorRoleId = DB::table('roles')->where('name', 'editor')->where('guard_name', 'admin')->value('id');
        if ($editorRoleId) {
            $editorModules = ['articles','article_categories','article_comments','faqs','banners','ads','static_pages','languages','social_media','products','categories'];
            $editorPermissionIds = DB::table('permissions')->where('guard_name', 'admin')->whereIn('module', $editorModules)
                ->where(function ($q) { $q->where('name', 'like', '%.view')->orWhere('name', 'like', '%.create')->orWhere('name', 'like', '%.edit')->orWhere('name', 'like', '%.manage')->orWhere('name', 'like', '%.export')->orWhere('name', 'like', '%.import'); })
                ->pluck('id');
            foreach ($editorPermissionIds as $permissionId) DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $editorRoleId]);
        }

        // Super admin always receives every permission. Existing roles/assignments are preserved.
        $superAdminRoleId = DB::table('roles')->where('name', 'super_admin')->where('guard_name', 'admin')->value('id');
        if ($superAdminRoleId) {
            foreach (DB::table('permissions')->where('guard_name', 'admin')->pluck('id') as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $superAdminRoleId,
                ]);
            }
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Preserve permissions and roles on rollback; deleting RBAC data can lock administrators out.
    }
};
