<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Only the repository sync set this flag, for archived or no longer accessible repositories; nothing to carry over
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('github_repository_mappings', function (Blueprint $table) {
            $table->dropColumn('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('github_repository_mappings', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false);
        });
    }
};
