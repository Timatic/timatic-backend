<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * These columns were the Google Calendar integration's, under names that read as though every user
 * held an OAuth token of their own. The integration keeps its grants in a table of its own now.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Both tables use the encrypted cast with the same key, so the ciphertext is copied as is.
        DB::table('users')
            ->whereNotNull('oauth_access_token')
            ->whereNotNull('oauth_refresh_token')
            ->eachById(function (object $user): void {
                DB::table('google_calendar_connections')->insert([
                    'user_id' => $user->id,
                    'access_token' => $user->oauth_access_token,
                    'refresh_token' => $user->oauth_refresh_token,
                    'expires_at' => $user->oauth_token_expires_at > 0
                        ? Carbon::createFromTimestamp($user->oauth_token_expires_at)
                        : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['oauth_access_token', 'oauth_refresh_token', 'oauth_token_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('oauth_access_token')->nullable()->after('family_name');
            $table->text('oauth_refresh_token')->nullable()->after('oauth_access_token');
            $table->integer('oauth_token_expires_at')->default(0)->after('oauth_refresh_token');
        });
    }
};
