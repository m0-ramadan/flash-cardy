<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guards against schema drift: earlier environments had the `activity_log`
 * table created by the spatie/laravel-activitylog migration, while the
 * squashed 2026_09_04 reconcile migration never covered it. The table is
 * required by `app/Models/Admin.php` (LogsActivity trait), so make sure it
 * exists on every environment without failing where it is already present.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $tableName = config('activitylog.table_name', 'activity_log');

        $schema = $connection ? Schema::connection($connection) : Schema::getFacadeRoot();

        if ($schema->hasTable($tableName)) {
            return;
        }

        $schema->create($tableName, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down(): void
    {
        // Intentionally no-op: the table is shared with older environments.
    }
};
