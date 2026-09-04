<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'transaction_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY transaction_id VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        // Kept nullable because checkout creates orders before gateway confirmation.
    }
};
