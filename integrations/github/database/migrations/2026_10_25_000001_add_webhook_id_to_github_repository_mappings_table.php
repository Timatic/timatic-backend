<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('github_repository_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('webhook_id')->nullable()->after('budget_id');
        });
    }

    public function down(): void
    {
        Schema::table('github_repository_mappings', function (Blueprint $table) {
            $table->dropColumn('webhook_id');
        });
    }
};
