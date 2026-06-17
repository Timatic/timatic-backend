<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('oauth_access_token')->nullable()->after('family_name');
            $table->text('oauth_refresh_token')->nullable()->after('oauth_access_token');
            $table->integer('oauth_token_expires_at')->default(0)->after('oauth_refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['oauth_access_token', 'oauth_refresh_token', 'oauth_token_expires_at']);
        });
    }
};
