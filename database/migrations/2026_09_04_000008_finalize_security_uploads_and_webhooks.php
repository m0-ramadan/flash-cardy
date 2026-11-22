<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('uploaded_files')) {
            Schema::create('uploaded_files', function (Blueprint $table) {
                $table->id(); $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('user_id')->nullable()->index(); $table->unsignedBigInteger('admin_id')->nullable()->index();
                $table->string('disk',40); $table->string('path',500); $table->string('original_name'); $table->string('stored_name');
                $table->string('extension',20); $table->string('mime_type',120); $table->unsignedBigInteger('size'); $table->string('sha256',64)->nullable()->index();
                $table->string('purpose',50)->index(); $table->boolean('is_public')->default(false); $table->json('metadata')->nullable(); $table->timestamps();
                $table->index(['user_id','purpose']);
            });
        }
        if (!Schema::hasTable('payment_webhook_events')) {
            Schema::create('payment_webhook_events', function (Blueprint $table) {
                $table->id(); $table->string('gateway',30)->index(); $table->string('event_key',191)->unique();
                $table->string('provider_transaction_id')->nullable()->index(); $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('payment_transaction_id')->nullable()->index(); $table->string('payload_hash',64)->index();
                $table->string('status',30)->default('received')->index(); $table->timestamp('processed_at')->nullable(); $table->json('payload')->nullable(); $table->timestamps();
            });
        }
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders','payment_method_id')) $table->unsignedBigInteger('payment_method_id')->nullable()->index();
                if (!Schema::hasColumn('orders','currency')) $table->string('currency',10)->default(config('commerce.base_currency','EGP'))->index();
                if (!Schema::hasColumn('orders','order_state')) $table->string('order_state',30)->default('pending')->index();
                if (!Schema::hasColumn('orders','payment_state')) $table->string('payment_state',30)->default('pending')->index();
                if (!Schema::hasColumn('orders','fulfilled_at')) $table->timestamp('fulfilled_at')->nullable();
            });
            if (Schema::hasColumn('orders','payment_state') && Schema::hasColumn('orders','status_payment')) {
                DB::table('orders')->whereNull('payment_state')->orWhere('payment_state','')->update(['payment_state'=>DB::raw('status_payment')]);
            }
            if (Schema::hasColumn('orders','order_state') && Schema::hasColumn('orders','status')) {
                DB::table('orders')->whereNull('order_state')->orWhere('order_state','')->update(['order_state'=>DB::raw('status')]);
            }
        }
        if (!Schema::hasTable('coupon_reservations')) {
            Schema::create('coupon_reservations', function (Blueprint $table) {
                $table->id(); $table->foreignId('coupon_id')->constrained('coupons')->restrictOnDelete(); $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable()->index(); $table->string('status',20)->default('reserved')->index();
                $table->decimal('discount_amount',14,4)->default(0); $table->timestamp('expires_at')->nullable()->index(); $table->timestamp('consumed_at')->nullable(); $table->timestamp('released_at')->nullable(); $table->timestamps();
                $table->unique('order_id'); $table->index(['coupon_id','status']); $table->index(['user_id','coupon_id','status']);
            });
        }
        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('payment_transactions','provider_intention_id')) $table->string('provider_intention_id')->nullable()->index();
                if (!Schema::hasColumn('payment_transactions','provider_transaction_id')) $table->string('provider_transaction_id')->nullable()->index();
                if (!Schema::hasColumn('payment_transactions','idempotency_key')) $table->string('idempotency_key',191)->nullable()->unique();
                if (!Schema::hasColumn('payment_transactions','webhook_processed_at')) $table->timestamp('webhook_processed_at')->nullable();
            });
        }
        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                if (!Schema::hasColumn('cart_items','design_file_id')) $table->unsignedBigInteger('design_file_id')->nullable()->index();
                if (!Schema::hasColumn('cart_items','customization_snapshot')) $table->json('customization_snapshot')->nullable();
                if (!Schema::hasColumn('cart_items','pricing_snapshot')) $table->json('pricing_snapshot')->nullable();
            });
        }
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('order_items','design_file_id')) $table->unsignedBigInteger('design_file_id')->nullable()->index();
                if (!Schema::hasColumn('order_items','pricing_snapshot')) $table->json('pricing_snapshot')->nullable();
            });
        }
    }
    public function down(): void { /* non-destructive finalization */ }
};
