<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A mapping without a user is shared with everyone. A mapping with one belongs to that user
     * alone, which is what local development domains need: every engineer runs their own.
     */
    public function up(): void
    {
        Schema::table('tracked_domains', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['domain', 'path']);
            $table->unique(['domain', 'path', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tracked_domains', function (Blueprint $table) {
            $table->dropUnique(['domain', 'path', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique(['domain', 'path']);
        });
    }
};
