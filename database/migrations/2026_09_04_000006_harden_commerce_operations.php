<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendProducts();
        $this->extendOrders();
        $this->extendOrderItems();
        $this->ensureInventoryTables();
        $this->ensureLike4AppTables();
        $this->ensureRefundTables();
        $this->extendWalletTables();
        $this->extendReviews();
        $this->extendArticles();
        $this->extendContactSupport();
        $this->extendAddresses();
        $this->ensureImportAuditTable();
        $this->backfillOperationalFields();
    }

    private function extendProducts(): void
    {
        if (!Schema::hasTable('products')) return;

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'inventory_type')) {
                $table->string('inventory_type', 20)->default('physical')->after('type')->index();
            }
            if (!Schema::hasColumn('products', 'track_stock')) {
                $table->boolean('track_stock')->default(true)->after('stock')->index();
            }
            if (!Schema::hasColumn('products', 'reserved_stock')) {
                $table->unsignedInteger('reserved_stock')->default(0)->after('stock');
            }
            if (!Schema::hasColumn('products', 'sold_stock')) {
                $table->unsignedBigInteger('sold_stock')->default(0)->after('reserved_stock');
            }
            if (!Schema::hasColumn('products', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by')->index();
            }
        });
    }

    private function extendOrders(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'shipping_address_snapshot')) {
                $table->json('shipping_address_snapshot')->nullable()->after('shipping_address');
            }
            if (!Schema::hasColumn('orders', 'billing_address_snapshot')) {
                $table->json('billing_address_snapshot')->nullable()->after('shipping_address_snapshot');
            }
            if (!Schema::hasColumn('orders', 'fulfillment_status')) {
                $table->string('fulfillment_status', 30)->default('unfulfilled')->after('status_payment')->index();
            }
            if (!Schema::hasColumn('orders', 'refund_status')) {
                $table->string('refund_status', 30)->default('none')->after('fulfillment_status')->index();
            }
            if (!Schema::hasColumn('orders', 'refunded_amount')) {
                $table->decimal('refunded_amount', 14, 4)->default(0)->after('refund_status');
            }
            if (!Schema::hasColumn('orders', 'inventory_released_at')) {
                $table->timestamp('inventory_released_at')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('orders', 'inventory_committed_at')) {
                $table->timestamp('inventory_committed_at')->nullable()->after('inventory_released_at');
            }
        });
    }

    private function extendOrderItems(): void
    {
        if (!Schema::hasTable('order_items')) return;

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'product_snapshot')) {
                $table->json('product_snapshot')->nullable()->after('selected_options');
            }
            if (!Schema::hasColumn('order_items', 'customization_snapshot')) {
                $table->json('customization_snapshot')->nullable()->after('product_snapshot');
            }
            if (!Schema::hasColumn('order_items', 'provider_product_id')) {
                $table->string('provider_product_id')->nullable()->after('provider_data')->index();
            }
            if (!Schema::hasColumn('order_items', 'provider_reference')) {
                $table->string('provider_reference', 191)->nullable()->after('provider_product_id')->index();
            }
            if (!Schema::hasColumn('order_items', 'provider_transaction_id')) {
                $table->string('provider_transaction_id')->nullable()->after('provider_reference')->index();
            }
            if (!Schema::hasColumn('order_items', 'provider_status')) {
                $table->string('provider_status', 30)->nullable()->after('provider_transaction_id')->index();
            }
            if (!Schema::hasColumn('order_items', 'fulfilled_at')) {
                $table->timestamp('fulfilled_at')->nullable()->after('provider_status');
            }
            if (!Schema::hasColumn('order_items', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('fulfilled_at');
            }
            if (!Schema::hasColumn('order_items', 'refunded_quantity')) {
                $table->unsignedInteger('refunded_quantity')->default(0)->after('quantity');
            }
        });
    }

    private function ensureInventoryTables(): void
    {
        if (!Schema::hasTable('stock_reservations')) {
            Schema::create('stock_reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->unsignedInteger('quantity');
                $table->string('status', 20)->default('reserved')->index();
                $table->string('reference', 191)->unique();
                $table->timestamp('reserved_at')->nullable();
                $table->timestamp('committed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->string('release_reason')->nullable();
                $table->timestamps();
                $table->unique('order_item_id');
                $table->index(['product_id', 'status']);
            });
        }

        if (!Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('order_item_id')->nullable()->index();
                $table->unsignedBigInteger('admin_id')->nullable()->index();
                $table->string('type', 30)->index();
                $table->integer('quantity');
                $table->integer('stock_before')->nullable();
                $table->integer('stock_after')->nullable();
                $table->unsignedInteger('reserved_before')->default(0);
                $table->unsignedInteger('reserved_after')->default(0);
                $table->string('reference', 191)->nullable()->unique();
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'created_at']);
            });
        }
    }

    private function ensureLike4AppTables(): void
    {
        if (Schema::hasTable('like4app_purchases')) return;

        Schema::create('like4app_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('provider_product_id');
            $table->string('stable_reference', 191)->unique();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('unit_index')->default(1);
            $table->unsignedInteger('requested_quantity')->default(1);
            $table->unsignedInteger('fulfilled_quantity')->default(0);
            $table->string('provider_transaction_id')->nullable()->index();
            $table->json('serial_codes')->nullable();
            $table->json('voucher_codes')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->unique(['order_item_id', 'unit_index'], 'like4app_item_unit_unique');
            $table->index(['order_id', 'status']);
        });
    }

    private function ensureRefundTables(): void
    {
        if (Schema::hasTable('refunds')) return;

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->unsignedBigInteger('payment_transaction_id')->nullable()->index();
            $table->unsignedBigInteger('requested_by_admin_id')->nullable()->index();
            $table->string('gateway', 30)->nullable()->index();
            $table->string('type', 20)->default('full');
            $table->string('status', 30)->default('requested')->index();
            $table->decimal('amount', 14, 4);
            $table->string('currency', 10)->default('EGP');
            $table->text('reason')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->json('provider_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });
    }

    private function extendWalletTables(): void
    {
        if (Schema::hasTable('user_wallets')) {
            Schema::table('user_wallets', function (Blueprint $table) {
                if (!Schema::hasColumn('user_wallets', 'base_currency')) {
                    $table->string('base_currency', 10)->nullable()->after('currency');
                }
            });
        }

        if (Schema::hasTable('idempotency_keys') && !Schema::hasColumn('idempotency_keys', 'response_payload')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->json('response_payload')->nullable()->after('response_hash');
            });
        }

        if (!Schema::hasTable('wallet_transfers')) {
            Schema::create('wallet_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 191)->unique();
                $table->foreignId('from_wallet_id')->constrained('user_wallets')->restrictOnDelete();
                $table->foreignId('to_wallet_id')->constrained('user_wallets')->restrictOnDelete();
                $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
                $table->decimal('amount', 15, 2);
                $table->string('currency', 10);
                $table->string('status', 20)->default('completed')->index();
                $table->unsignedBigInteger('debit_entry_id')->nullable()->index();
                $table->unsignedBigInteger('credit_entry_id')->nullable()->index();
                $table->string('idempotency_key', 191)->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->string('account_holder')->nullable();
                $table->string('account_identifier')->nullable();
                $table->string('currency', 3)->nullable();
                $table->string('image')->nullable();
                $table->string('type')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        } else {
            Schema::table('bank_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('bank_accounts', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
                }
                if (!Schema::hasColumn('bank_accounts', 'account_holder')) {
                    $table->string('account_holder')->nullable();
                }
                if (!Schema::hasColumn('bank_accounts', 'account_identifier')) {
                    $table->string('account_identifier')->nullable();
                }
                if (!Schema::hasColumn('bank_accounts', 'currency')) {
                    $table->string('currency', 10)->default('EGP')->index();
                }
            });
        }
    }

    private function extendReviews(): void
    {
        if (!Schema::hasTable('reviews')) return;

        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'is_verified_purchase')) {
                $table->boolean('is_verified_purchase')->default(false)->after('is_approved')->index();
            }
            if (!Schema::hasColumn('reviews', 'moderated_by')) {
                $table->unsignedBigInteger('moderated_by')->nullable()->index();
            }
            if (!Schema::hasColumn('reviews', 'moderated_at')) {
                $table->timestamp('moderated_at')->nullable();
            }
        });
    }

    private function extendArticles(): void
    {
        if (Schema::hasTable('articles')) {
            Schema::table('articles', function (Blueprint $table) {
                if (!Schema::hasColumn('articles', 'status')) {
                    $table->string('status', 20)->default('published')->after('is_active')->index();
                }
            });
        }

        if (Schema::hasTable('article_comments')) {
            Schema::table('article_comments', function (Blueprint $table) {
                if (!Schema::hasColumn('article_comments', 'moderated_by')) {
                    $table->unsignedBigInteger('moderated_by')->nullable()->index();
                }
                if (!Schema::hasColumn('article_comments', 'moderated_at')) {
                    $table->timestamp('moderated_at')->nullable();
                }
                if (!Schema::hasColumn('article_comments', 'spam_score')) {
                    $table->unsignedTinyInteger('spam_score')->default(0)->index();
                }
            });
        }
    }

    private function extendContactSupport(): void
    {
        if (!Schema::hasTable('contact_us_replies')) return;

        Schema::table('contact_us_replies', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_us_replies', 'sender_id')) {
                $table->unsignedBigInteger('sender_id')->nullable()->after('sender_type')->index();
            }
        });
    }

    private function extendAddresses(): void
    {
        if (!Schema::hasTable('user_addresses')) return;

        Schema::table('user_addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('user_addresses', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('type')->index();
            }
            if (!Schema::hasColumn('user_addresses', 'address_role')) {
                $table->string('address_role', 20)->default('shipping')->after('is_default')->index();
            }
        });
    }

    private function ensureImportAuditTable(): void
    {
        if (Schema::hasTable('import_export_audits')) return;

        Schema::create('import_export_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('operation', 20)->index();
            $table->string('entity', 50)->index();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function backfillOperationalFields(): void
    {
        if (Schema::hasTable('products')) {
            DB::table('products')->whereNotNull('external_id')->update([
                'inventory_type' => 'digital',
                'track_stock' => false,
            ]);
            DB::table('products')->whereNull('external_id')->whereNull('inventory_type')->update([
                'inventory_type' => 'physical',
            ]);
        }

        if (Schema::hasTable('user_wallets')) {
            DB::table('user_wallets')->whereNull('base_currency')->update(['base_currency' => config('commerce.base_currency', 'EGP')]);
        }

        if (Schema::hasTable('orders')) {
            DB::table('orders')->whereIn('status', ['completed', 'delivered'])->update([
                'fulfillment_status' => 'fulfilled',
            ]);
            DB::table('orders')->where('status', 'cancelled')->update([
                'fulfillment_status' => 'cancelled',
            ]);
        }

        if (Schema::hasTable('articles')) {
            DB::table('articles')->where('is_active', 0)->update(['status' => 'draft']);
            DB::table('articles')->where('is_active', 1)->whereNotNull('published_at')->update(['status' => 'published']);
        }

        if (Schema::hasTable('contact_us_replies') && Schema::hasColumn('contact_us_replies', 'sender_id')) {
            DB::table('contact_us_replies')->where('sender_type', 'user')->whereNull('sender_id')->update(['sender_id' => DB::raw('user_id')]);
            if (Schema::hasColumn('contact_us_replies', 'admin_id')) {
                DB::table('contact_us_replies')->where('sender_type', 'admin')->whereNull('sender_id')->update(['sender_id' => DB::raw('admin_id')]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. Operational data created by this migration may contain
        // paid orders, vouchers, stock movements or financial records and must not be dropped automatically.
    }
};
