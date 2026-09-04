<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $table) {
                $table->id();
                $table->string('from_currency', 10);
                $table->string('to_currency', 10);
                $table->decimal('rate', 20, 10);
                $table->string('provider')->nullable();
                $table->string('source')->nullable();
                $table->boolean('is_live')->default(true);
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_to')->nullable();
                $table->timestamps();

                $table->index(['from_currency', 'to_currency']);
                $table->index('provider');
            });
        }

        if (! Schema::hasTable('exchange_rate_logs')) {
            Schema::create('exchange_rate_logs', function (Blueprint $table) {
                $table->id();
                $table->string('from_currency', 10);
                $table->string('to_currency', 10);
                $table->decimal('original_amount', 14, 4);
                $table->decimal('converted_amount', 14, 4);
                $table->decimal('exchange_rate', 20, 10);
                $table->string('provider')->nullable();
                $table->ipAddress('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index(['from_currency', 'to_currency']);
                $table->index('provider');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_logs');
        Schema::dropIfExists('exchange_rates');
    }
};
