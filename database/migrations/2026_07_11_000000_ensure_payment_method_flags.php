<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_methods', 'is_payment')) {
                $table->boolean('is_payment')->default(true)->after('is_active');
            }

            if (! Schema::hasColumn('payment_methods', 'is_wallet')) {
                $table->boolean('is_wallet')->default(false)->after('is_payment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (Schema::hasColumn('payment_methods', 'is_wallet')) {
                $table->dropColumn('is_wallet');
            }

            if (Schema::hasColumn('payment_methods', 'is_payment')) {
                $table->dropColumn('is_payment');
            }
        });
    }
};
