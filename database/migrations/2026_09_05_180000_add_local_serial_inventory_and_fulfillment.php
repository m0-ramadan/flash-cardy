<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'subtitle')) $table->string('subtitle')->nullable()->after('name');
            if (!Schema::hasColumn('products', 'type')) $table->string('type', 30)->default('product')->after('subtitle');
            if (!Schema::hasColumn('products', 'inventory_type')) $table->string('inventory_type', 20)->default('physical')->after('type');
            if (!Schema::hasColumn('products', 'track_stock')) $table->boolean('track_stock')->default(true)->after('inventory_type');
            if (!Schema::hasColumn('products', 'max_quantity')) $table->unsignedInteger('max_quantity')->default(0)->after('stock');
            if (!Schema::hasColumn('products', 'instructions')) $table->text('instructions')->nullable();
            if (!Schema::hasColumn('products', 'url')) $table->string('url', 500)->nullable();
            if (!Schema::hasColumn('products', 'fulfillment_source')) $table->string('fulfillment_source', 30)->default('none')->after('external_id');
            if (!Schema::hasColumn('products', 'fulfillment_mode')) $table->string('fulfillment_mode', 20)->default('manual')->after('fulfillment_source');
            if (!Schema::hasColumn('products', 'allow_serial_backorder')) $table->boolean('allow_serial_backorder')->default(true)->after('fulfillment_mode');
            if (!Schema::hasColumn('products', 'brand')) $table->string('brand', 150)->nullable()->after('subtitle');
            if (!Schema::hasColumn('products', 'region')) $table->string('region', 150)->nullable()->after('brand');
            if (!Schema::hasColumn('products', 'denomination')) $table->string('denomination', 100)->nullable()->after('region');
            if (!Schema::hasColumn('products', 'validity_text')) $table->string('validity_text', 255)->nullable()->after('denomination');
            if (!Schema::hasColumn('products', 'is_featured')) $table->boolean('is_featured')->default(false)->index();
            if (!Schema::hasColumn('products', 'is_best_seller')) $table->boolean('is_best_seller')->default(false)->index();
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'fulfillment_source_snapshot')) $table->string('fulfillment_source_snapshot', 30)->nullable()->after('provider_status');
            if (!Schema::hasColumn('order_items', 'fulfillment_mode_snapshot')) $table->string('fulfillment_mode_snapshot', 20)->nullable()->after('fulfillment_source_snapshot');
            if (!Schema::hasColumn('order_items', 'serial_status')) $table->string('serial_status', 30)->default('not_required')->after('fulfillment_mode_snapshot');
        });

        if (!Schema::hasTable('product_serials')) {
            Schema::create('product_serials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->char('code_hash', 64)->unique();
                $table->text('code'); // encrypted cast in the model
                $table->text('pin')->nullable(); // encrypted cast in the model
                $table->timestamp('expires_at')->nullable()->index();
                $table->string('status', 24)->default('available')->index();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
                $table->timestamp('reserved_at')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('disabled_at')->nullable();
                $table->string('source', 30)->default('manual');
                $table->string('batch_reference', 100)->nullable()->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();
                $table->index(['product_id', 'status', 'expires_at'], 'product_serials_available_idx');
                $table->index(['order_item_id', 'status'], 'product_serials_item_status_idx');
            });
        }

        // Preserve current Like4App behaviour. Existing linked products continue to auto-fulfil remotely.
        DB::table('products')->whereNotNull('external_id')->where('external_id', '<>', '')->update([
            'fulfillment_source' => 'like4app',
            'fulfillment_mode' => 'automatic',
            'inventory_type' => 'digital',
            'track_stock' => false,
        ]);

        // Snapshot existing items so later product changes never alter historical fulfilment rules.
        if (Schema::hasColumn('order_items', 'fulfillment_source_snapshot')) {
            DB::statement("UPDATE order_items oi JOIN products p ON p.id = oi.product_id SET oi.fulfillment_source_snapshot = COALESCE(oi.fulfillment_source_snapshot, p.fulfillment_source), oi.fulfillment_mode_snapshot = COALESCE(oi.fulfillment_mode_snapshot, p.fulfillment_mode), oi.serial_status = CASE WHEN COALESCE(oi.fulfillment_source_snapshot,p.fulfillment_source)='none' THEN 'not_required' WHEN oi.fulfilled_at IS NOT NULL THEN 'delivered' ELSE 'pending' END");
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. This migration protects production order/code history.
        // Rollback must never delete issued voucher codes or fulfilment snapshots.
    }
};
