<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Authentication / administration compatibility columns.
        if (Schema::hasTable('admins') && !Schema::hasColumn('admins', 'is_active')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('remember_token')->index();
            });
        } elseif (Schema::hasTable('admins') && Schema::hasColumn('admins', 'is_active')) {
            DB::table('admins')->whereNull('is_active')->update(['is_active' => true]);
        }

        if (Schema::hasTable('admins') && !Schema::hasColumn('admins', 'last_login_at')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->timestamp('last_login_at')->nullable()->after('is_active');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('image')->index();
                }
                if (!Schema::hasColumn('users', 'deactivated_at')) {
                    $table->timestamp('deactivated_at')->nullable()->after('is_active');
                }
                if (!Schema::hasColumn('users', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable()->after('deactivated_at');
                }
            });
        }

        if (Schema::hasTable('otp_verifications')) {
            Schema::table('otp_verifications', function (Blueprint $table) {
                if (!Schema::hasColumn('otp_verifications', 'attempts')) {
                    $table->unsignedTinyInteger('attempts')->default(0)->after('otp');
                }
                if (!Schema::hasColumn('otp_verifications', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('expires_at');
                }
                if (!Schema::hasColumn('otp_verifications', 'purpose')) {
                    $table->string('purpose', 50)->default('password_reset')->after('email');
                }
            });
        }

        if (!Schema::hasTable('admin_password_reset_tokens')) {
            Schema::create('admin_password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
                $table->index('created_at');
            });
        }

        if (!Schema::hasTable('social_identities')) {
            Schema::create('social_identities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('provider', 30);
                $table->string('provider_user_id');
                $table->string('email')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('last_verified_at')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'provider_user_id'], 'social_provider_user_unique');
                $table->unique(['user_id', 'provider'], 'social_user_provider_unique');
                $table->index('email');
            });
        }

        if (!Schema::hasTable('data_repair_archives')) {
            Schema::create('data_repair_archives', function (Blueprint $table) {
                $table->id();
                $table->string('source_table', 100)->index();
                $table->string('source_key', 191)->nullable()->index();
                $table->string('reason', 255);
                $table->json('payload');
                $table->timestamp('archived_at');
                $table->timestamps();
            });
        }

        // Tables that exist in the production dump but did not have a reliable create migration in source.
        $this->ensureRuntimeOnlyTables();

        // Tables referenced by current models/controllers but absent from the supplied production dump.
        $this->ensureCustomizationTables();
        $this->ensureWalletSupportTables();

        // Compatibility columns referenced by models/controllers.
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'terms')) {
                    $table->text('terms')->nullable()->after('meta_keywords');
                }
                if (!Schema::hasColumn('products', 'instructions')) {
                    $table->text('instructions')->nullable()->after('terms');
                }
            });
        }

        if (Schema::hasTable('favourites') && !Schema::hasColumn('favourites', 'type')) {
            Schema::table('favourites', function (Blueprint $table) {
                $table->string('type', 50)->nullable()->after('product_id');
            });
        }

        $this->ensureCartAndOrderCustomizationColumns();
        $this->ensureSafeIndexes();
    }

    private function ensureRuntimeOnlyTables(): void
    {
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('key')->unique();
                $table->text('value');
            });
        }

        if (!Schema::hasTable('translations')) {
            Schema::create('translations', function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->string('locale', 10);
                $table->text('value');
                $table->timestamps();
                $table->unique(['key', 'locale']);
            });
        }

        if (!Schema::hasTable('important_models')) {
            Schema::create('important_models', function (Blueprint $table) {
                $table->id();
                $table->text('important_text');
                $table->string('model');
                $table->timestamps();
                $table->index('model');
            });
        }

        if (!Schema::hasTable('article_translations')) {
            Schema::create('article_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
                $table->string('language_code', 10);
                $table->string('title');
                $table->text('content')->nullable();
                $table->text('excerpt')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->text('meta_keywords')->nullable();
                $table->timestamps();
                $table->unique(['article_id', 'language_code']);
            });
        }

        if (!Schema::hasTable('contact_us_replies')) {
            Schema::create('contact_us_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_us_id')->constrained('contact_us')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->text('message');
                $table->string('sender_type', 20);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['sender_type', 'user_id']);
                $table->index(['sender_type', 'admin_id']);
            });
        } elseif (!Schema::hasColumn('contact_us_replies', 'admin_id')) {
            Schema::table('contact_us_replies', function (Blueprint $table) {
                $table->unsignedBigInteger('admin_id')->nullable()->after('user_id')->index();
            });
        }

        if (!Schema::hasTable('order_logs')) {
            Schema::create('order_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('action', 100);
                $table->string('provider', 50)->nullable();
                $table->json('request_data')->nullable();
                $table->json('response_data')->nullable();
                $table->text('error_message')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['order_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
                $table->string('gateway');
                $table->string('provider_payment_id')->nullable();
                $table->string('parent_payment_id')->nullable();
                $table->string('purchase_id')->nullable();
                $table->string('status');
                $table->string('provider_status')->nullable();
                $table->decimal('original_amount', 14, 4);
                $table->string('original_currency', 10);
                $table->decimal('exchange_rate', 20, 10)->nullable();
                $table->decimal('price_amount', 14, 4)->nullable();
                $table->string('price_currency', 10)->nullable();
                $table->decimal('pay_amount', 40, 20)->nullable();
                $table->decimal('actually_paid', 40, 20)->nullable();
                $table->string('pay_currency', 20)->nullable();
                $table->text('pay_address')->nullable();
                $table->string('payin_extra_id')->nullable();
                $table->decimal('outcome_amount', 40, 20)->nullable();
                $table->string('outcome_currency', 20)->nullable();
                $table->boolean('is_fixed_rate')->nullable();
                $table->boolean('is_fee_paid_by_user')->nullable();
                $table->boolean('requires_manual_review')->default(false);
                $table->text('failure_reason')->nullable();
                $table->json('initial_payload')->nullable();
                $table->json('provider_response')->nullable();
                $table->json('last_webhook_payload')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();
                $table->index(['gateway', 'status']);
                $table->index('provider_payment_id');
            });
        }

        if (!Schema::hasTable('user_wallets')) {
            Schema::create('user_wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->decimal('balance', 15, 2)->default(0);
                $table->decimal('held_balance', 15, 2)->default(0);
                $table->string('currency', 10)->default('EGP');
                $table->string('status', 20)->default('active');
                $table->decimal('daily_limit', 15, 2)->default(10000);
                $table->decimal('monthly_limit', 15, 2)->default(50000);
                $table->decimal('total_deposits_today', 15, 2)->default(0);
                $table->decimal('total_withdrawals_today', 15, 2)->default(0);
                $table->decimal('total_transfers_today', 15, 2)->default(0);
                $table->timestamp('last_transaction_at')->nullable();
                $table->integer('version')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('wallet_id');
                $table->string('wallet_type', 20);
                $table->string('owner_type', 20);
                $table->unsignedBigInteger('owner_id');
                $table->string('type', 30);
                $table->decimal('amount', 15, 2);
                $table->decimal('balance_before', 15, 2);
                $table->decimal('balance_after', 15, 2);
                $table->decimal('available_balance_before', 15, 2)->nullable();
                $table->decimal('available_balance_after', 15, 2)->nullable();
                $table->string('payment_method', 50)->nullable();
                $table->string('payment_transaction_id', 100)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('pending');
                $table->string('reference', 100)->nullable();
                $table->unsignedBigInteger('related_entry_id')->nullable();
                $table->string('related_owner_type', 20)->nullable();
                $table->unsignedBigInteger('related_owner_id')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamps();
                $table->index(['owner_type', 'owner_id']);
                $table->index(['wallet_type', 'wallet_id']);
                $table->index('reference');
            });
        }
    }

    private function ensureCustomizationTables(): void
    {
        if (!Schema::hasTable('colors')) {
            Schema::create('colors', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('image')->nullable();
                $table->string('hex_code')->nullable();
                $table->decimal('additional_price', 10, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('materials')) {
            Schema::create('materials', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('printing_methods')) {
            Schema::create('printing_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('base_price', 10, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('print_locations')) {
            Schema::create('print_locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type', 20)->default('print');
                $table->decimal('additional_price', 10, 2)->default(0);
                $table->timestamps();
                $table->index('type');
            });
        }

        if (!Schema::hasTable('embroider_locations')) {
            Schema::create('embroider_locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('additional_price', 10, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('design_services')) {
            Schema::create('design_services', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('price', 10, 2)->default(0);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pricing_tiers')) {
            Schema::create('pricing_tiers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->integer('quantity');
                $table->decimal('price_per_unit', 10, 4);
                $table->boolean('is_sample')->default(false);
                $table->decimal('discount_percentage', 8, 2)->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'quantity', 'is_sample'], 'pricing_tier_unique');
            });
        }

        if (!Schema::hasTable('product_options')) {
            Schema::create('product_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->bigInteger('external_option_id')->nullable();
                $table->bigInteger('external_detail_id')->nullable();
                $table->string('option_name');
                $table->string('option_value');
                $table->decimal('additional_price', 10, 2)->default(0);
                $table->boolean('is_required')->default(false);
                $table->timestamps();
                $table->index('external_option_id');
                $table->index('external_detail_id');
            });
        }

        if (!Schema::hasTable('product_size_tiers')) {
            Schema::create('product_size_tiers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('size_id')->constrained('sizes')->cascadeOnDelete();
                $table->integer('quantity');
                $table->decimal('price_per_unit', 10, 2);
                $table->timestamps();
                $table->unique(['product_id', 'size_id', 'quantity'], 'product_size_qty_unique');
            });
        }

        if (!Schema::hasTable('delivery_times')) {
            Schema::create('delivery_times', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->integer('from_days')->nullable();
                $table->integer('to_days')->nullable();
                $table->integer('min_days')->nullable();
                $table->integer('max_days')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index('product_id');
            });
        }

        $this->createPivotIfMissing('color_product', 'color_id', 'colors');
        $this->createPivotIfMissing('material_product', 'material_id', 'materials', true);
        $this->createPivotIfMissing('printing_method_product', 'printing_method_id', 'printing_methods');
        $this->createPivotIfMissing('product_print_methods', 'printing_method_id', 'printing_methods');
        $this->createPivotIfMissing('print_location_product', 'print_location_id', 'print_locations');
    }

    private function createPivotIfMissing(string $name, string $relatedKey, string $relatedTable, bool $withQuantity = false): void
    {
        if (Schema::hasTable($name)) {
            return;
        }
        Schema::create($name, function (Blueprint $table) use ($name, $relatedKey, $relatedTable, $withQuantity) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId($relatedKey)->constrained($relatedTable)->cascadeOnDelete();
            $table->decimal('additional_price', 10, 2)->nullable();
            if ($withQuantity) {
                $table->string('unit')->nullable();
                $table->decimal('quantity', 10, 2)->nullable();
            }
            $table->timestamps();
            $table->unique(['product_id', $relatedKey], $name . '_product_related_unique');
        });
    }

    private function ensureWalletSupportTables(): void
    {
        if (!Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('type', 50)->nullable();
                $table->timestamps();
                $table->index('is_active');
            });
        }

        if (!Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->string('key', 191)->unique();
                $table->string('request_hash', 128);
                $table->string('response_hash', 128)->nullable();
                $table->string('status', 30)->default('processing');
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->string('resource_type')->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->string('owner_type', 50)->nullable();
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->timestamps();
                $table->index(['owner_type', 'owner_id']);
            });
        }

        if (!Schema::hasTable('driver_wallets')) {
            Schema::create('driver_wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_id')->unique();
                $table->decimal('balance', 15, 2)->default(0);
                $table->decimal('held_balance', 15, 2)->default(0);
                $table->string('currency', 10)->default('EGP');
                $table->string('status', 20)->default('active');
                $table->timestamp('last_transaction_at')->nullable();
                $table->decimal('daily_limit', 15, 2)->default(10000);
                $table->decimal('monthly_limit', 15, 2)->default(50000);
                $table->decimal('total_earnings_today', 15, 2)->default(0);
                $table->decimal('total_withdrawals_today', 15, 2)->default(0);
                $table->decimal('total_cashouts_today', 15, 2)->default(0);
                $table->integer('version')->default(1);
                $table->timestamps();
            });
        }
    }

    private function ensureCartAndOrderCustomizationColumns(): void
    {
        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                foreach (['size_id', 'color_id', 'printing_method_id', 'design_service_id', 'material_id'] as $column) {
                    if (!Schema::hasColumn('cart_items', $column)) {
                        $table->unsignedBigInteger($column)->nullable();
                    }
                }
                foreach (['print_locations', 'embroider_locations', 'selected_options'] as $column) {
                    if (!Schema::hasColumn('cart_items', $column)) {
                        $table->json($column)->nullable();
                    }
                }
                if (!Schema::hasColumn('cart_items', 'image_design')) {
                    $table->string('image_design')->nullable();
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                foreach (['size_id', 'color_id', 'printing_method_id', 'design_service_id', 'material_id', 'pricing_tier_id'] as $column) {
                    if (!Schema::hasColumn('order_items', $column)) {
                        $table->unsignedBigInteger($column)->nullable();
                    }
                }
                foreach (['print_locations', 'embroider_locations', 'selected_options', 'provider_data', 'serial_codes'] as $column) {
                    if (!Schema::hasColumn('order_items', $column)) {
                        $table->json($column)->nullable();
                    }
                }
                if (!Schema::hasColumn('order_items', 'image_design')) {
                    $table->string('image_design')->nullable();
                }
                if (!Schema::hasColumn('order_items', 'voucher_code')) {
                    $table->text('voucher_code')->nullable();
                }
                if (!Schema::hasColumn('order_items', 'note')) {
                    $table->text('note')->nullable();
                }
            });
        }
    }

    private function ensureSafeIndexes(): void
    {
        // Add only indexes that can be created without modifying existing business data.
        if (Schema::hasTable('users')) {
            foreach (['google_id', 'facebook_id', 'apple_id'] as $column) {
                if (!Schema::hasColumn('users', $column)) {
                    continue;
                }
                // The canonical uniqueness is social_identities. Keep legacy IDs indexed, not forced unique,
                // because old production data may contain duplicates that must be reviewed first.
                try {
                    Schema::table('users', function (Blueprint $table) use ($column) {
                        $table->index($column, 'users_' . $column . '_lookup_idx');
                    });
                } catch (\Throwable $e) {
                    // Index may already exist under a historical name. Safe to continue.
                }
            }
        }
    }

    public function down(): void
    {
        // Deliberately conservative: this reconciliation migration must not destroy production data.
        // New compatibility tables/columns are retained on rollback. Future cleanup migrations can
        // remove deprecated fields after a verified deprecation window.
    }
};
