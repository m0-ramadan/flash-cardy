<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_audit_logs')) {
            Schema::create('admin_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('route_name')->nullable()->index();
                $table->string('method', 10);
                $table->string('path', 500);
                $table->string('action', 100)->nullable()->index();
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->json('request_summary')->nullable();
                $table->ipAddress('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
                $table->index(['admin_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // Intentionally preserve security audit history on rollback.
    }
};
