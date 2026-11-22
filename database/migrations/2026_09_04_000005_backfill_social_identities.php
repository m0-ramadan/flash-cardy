<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('social_identities')) return;

        foreach (['google' => 'google_id', 'facebook' => 'facebook_id', 'apple' => 'apple_id'] as $provider => $column) {
            if (!Schema::hasColumn('users', $column)) continue;

            DB::table('users')->whereNotNull($column)->where($column, '<>', '')
                ->orderBy('id')->chunkById(250, function ($users) use ($provider, $column) {
                    foreach ($users as $user) {
                        $conflict = DB::table('social_identities')
                            ->where('provider', $provider)
                            ->where('provider_user_id', $user->{$column})
                            ->where('user_id', '<>', $user->id)
                            ->exists();
                        if ($conflict) continue;

                        DB::table('social_identities')->updateOrInsert(
                            ['user_id' => $user->id, 'provider' => $provider],
                            [
                                'provider_user_id' => $user->{$column},
                                'email' => $user->email,
                                'metadata' => json_encode(['source' => 'legacy_backfill'], JSON_UNESCAPED_UNICODE),
                                'last_verified_at' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }, 'id');
        }
    }

    public function down(): void
    {
        // Keep linked identities; removing them could break existing social-login accounts.
    }
};
