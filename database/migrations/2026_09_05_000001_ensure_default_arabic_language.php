<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('languages')) {
            return;
        }

        if (!Schema::hasColumn('languages', 'is_default')) {
            Schema::table('languages', function (Blueprint $table) {
                $table->boolean('is_default')->default(false);
            });
        }

        if (!Schema::hasColumn('languages', 'direction')) {
            Schema::table('languages', function (Blueprint $table) {
                $table->string('direction', 5)->default('rtl');
            });
        }

        if (!Schema::hasColumn('languages', 'sort_order')) {
            Schema::table('languages', function (Blueprint $table) {
                $table->integer('sort_order')->default(0);
            });
        }

        $now = now();

        DB::table('languages')->updateOrInsert(
            ['code' => 'ar'],
            [
                'name' => 'العربية',
                'image_path' => 'languages/flags/ar.png',
                'is_active' => true,
                'direction' => 'rtl',
                'sort_order' => 1,
                'is_default' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        // Deliberately keep the Arabic language protected on rollback.
    }
};
