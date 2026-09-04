<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('status_payment');
            }

            if (! Schema::hasColumn('orders', 'cart_id')) {
                $table->foreignId('cart_id')->nullable()->after('user_id')->constrained('carts')->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'session_id')) {
                $table->string('session_id')->nullable()->index()->after('cart_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'cart_id')) {
                $table->dropConstrainedForeignId('cart_id');
            }

            if (Schema::hasColumn('orders', 'session_id')) {
                $table->dropColumn('session_id');
            }

            if (Schema::hasColumn('orders', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
        });
    }
};
