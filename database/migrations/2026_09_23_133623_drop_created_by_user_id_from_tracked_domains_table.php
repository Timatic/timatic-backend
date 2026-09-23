<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('tracked_domains', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('is_internal')->constrained('users')->nullOnDelete();
        });
    }
};
