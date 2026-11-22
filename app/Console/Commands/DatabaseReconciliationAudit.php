<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseReconciliationAudit extends Command
{
    protected $signature = 'db:reconciliation-audit {--json : Output JSON}';
    protected $description = 'Read-only production schema/data integrity audit used before and after stabilization migrations.';

    public function handle(): int
    {
        $checks = [];
        $requiredTables = [
            'admins','users','otp_verifications','social_identities','admin_password_reset_tokens',
            'roles','permissions','model_has_roles','model_has_permissions','role_has_permissions',
            'admin_audit_logs','data_repair_archives','payment_transactions','order_logs','contact_us_replies',
            'user_wallets','ledger_entries','idempotency_keys','bank_accounts',
            'colors','materials','printing_methods','print_locations','embroider_locations','design_services',
            'pricing_tiers','product_options','product_size_tiers',
        ];

        foreach ($requiredTables as $table) {
            $checks[] = ['check' => "table:{$table}", 'ok' => Schema::hasTable($table), 'details' => null];
        }

        $requiredColumns = [
            'admins' => ['is_active','last_login_at'],
            'users' => ['is_active','deactivated_at','last_login_at'],
            'otp_verifications' => ['purpose','attempts','verified_at','used_at','expires_at'],
            'products' => ['terms','instructions'],
            'favourites' => ['type'],
            'cart_items' => ['material_id','color_id','printing_method_id','print_locations','embroider_locations','selected_options','design_service_id','image_design'],
            'order_items' => ['material_id','color_id','printing_method_id','print_locations','embroider_locations','selected_options','design_service_id','image_design'],
        ];
        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                $checks[] = ['check' => "column:{$table}.{$column}", 'ok' => Schema::hasTable($table) && Schema::hasColumn($table, $column), 'details' => null];
            }
        }

        foreach ([
            ['cart_items','product_id','products'], ['favourites','product_id','products'], ['reviews','product_id','products'],
            ['features','product_id','products'], ['offer_product','product_id','products'], ['offer_product','offer_id','offers'],
        ] as [$child,$fk,$parent]) {
            if (!Schema::hasTable($child) || !Schema::hasTable($parent) || !Schema::hasColumn($child,$fk)) continue;
            $orphans = DB::table($child)->leftJoin($parent, "{$child}.{$fk}", '=', "{$parent}.id")
                ->whereNotNull("{$child}.{$fk}")->whereNull("{$parent}.id")->count();
            $checks[] = ['check' => "orphans:{$child}.{$fk}", 'ok' => $orphans === 0, 'details' => ['count' => $orphans]];
        }

        $failed = collect($checks)->where('ok', false)->count();
        $summary = ['ok' => $failed === 0, 'failed' => $failed, 'total' => count($checks), 'checks' => $checks];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Check','Result','Details'], collect($checks)->map(fn($c) => [
                $c['check'], $c['ok'] ? 'PASS' : 'FAIL', $c['details'] ? json_encode($c['details']) : ''
            ])->all());
            $this->info("Checks: ".count($checks).", failed: {$failed}");
        }
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
