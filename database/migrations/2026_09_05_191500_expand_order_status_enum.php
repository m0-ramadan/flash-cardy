<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The `orders.status` column was an ENUM that predates the payment-based
 * lifecycle and therefore rejected the `paid` / `refunded` values written by
 * CompletePaidOrderService and OrderLifecycleService ("Data truncated for
 * column 'status'").
 *
 * The catalogue is digital, so shipping states are removed from the enum.
 */
return new class extends Migration
{
    private array $statuses = ['pending', 'paid', 'processing', 'completed', 'cancelled', 'refunded'];

    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return; // enum alteration is MySQL specific
        }

        // Make sure no legacy value disappears silently.
        DB::table('orders')->whereIn('status', ['shipped', 'delivered'])->update(['status' => 'completed']);

        $values = implode("','", $this->statuses);
        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('{$values}') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('orders')->whereIn('status', ['paid', 'refunded'])->update(['status' => 'pending']);
        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('pending','processing','completed','cancelled','delivered') NOT NULL DEFAULT 'pending'");
    }
};
