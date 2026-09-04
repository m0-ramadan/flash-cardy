<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       
        // Schema::create('payment_transactions', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('order_id')->constrained()->cascadeOnDelete();
        //     $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
        //     $table->string('gateway')->index();
        //     $table->string('provider_payment_id')->nullable()->unique();
        //     $table->string('parent_payment_id')->nullable()->index();
        //     $table->string('purchase_id')->nullable();
        //     $table->string('status')->index();
        //     $table->string('provider_status')->nullable();
        //     $table->decimal('original_amount', 14, 4);
        //     $table->string('original_currency', 10);
        //     $table->decimal('exchange_rate', 20, 10)->nullable();
        //     $table->decimal('price_amount', 14, 4)->nullable();
        //     $table->string('price_currency', 10)->nullable();
        //     $table->decimal('pay_amount', 40, 20)->nullable();
        //     $table->decimal('actually_paid', 40, 20)->nullable();
        //     $table->string('pay_currency', 20)->nullable();
        //     $table->text('pay_address')->nullable();
        //     $table->string('payin_extra_id')->nullable();
        //     $table->decimal('outcome_amount', 40, 20)->nullable();
        //     $table->string('outcome_currency', 20)->nullable();
        //     $table->boolean('is_fixed_rate')->default(false);
        //     $table->boolean('is_fee_paid_by_user')->default(false);
        //     $table->boolean('requires_manual_review')->default(false);
        //     $table->text('failure_reason')->nullable();
        //     $table->json('initial_payload')->nullable();
        //     $table->json('provider_response')->nullable();
        //     $table->json('last_webhook_payload')->nullable();
        //     $table->json('metadata')->nullable();
        //     $table->timestamp('expires_at')->nullable();
        //     $table->timestamp('paid_at')->nullable();
        //     $table->timestamp('failed_at')->nullable();
        //     $table->timestamps();
        // });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};