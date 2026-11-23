<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalises legacy order statuses.
 *
 * The catalogue is digital (game cards / subscriptions) and has no shipping
 * step, so `shipped` / `delivered` are no longer valid order states. They are
 * folded into `completed`, while orders that are already paid but still sit on
 * `pending` are moved to the new `paid` state.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        // Legacy shipping states -> completed (fulfilment is what completes a digital order).
        DB::table('orders')
            ->whereIn('status', ['shipped', 'delivered'])
            ->update([
                'status' => 'completed',
                'fulfillment_status' => DB::raw("CASE WHEN fulfillment_status IN ('shipped','delivered') THEN 'fulfilled' ELSE fulfillment_status END"),
            ]);

        DB::table('orders')
            ->whereIn('order_state', ['shipped', 'delivered'])
            ->update(['order_state' => 'completed']);

        // Paid orders must not stay "awaiting payment".
        DB::table('orders')
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('status_payment', 'paid')->orWhere('payment_state', 'paid');
            })
            ->update(['status' => 'paid']);
    }

    public function down(): void
    {
        // Data normalisation is intentionally not reversible.
    }
};
