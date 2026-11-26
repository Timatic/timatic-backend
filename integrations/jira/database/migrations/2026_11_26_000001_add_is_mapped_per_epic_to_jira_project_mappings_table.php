<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jira_project_mappings', function (Blueprint $table) {
            $table->boolean('is_mapped_per_epic')->default(false)->after('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('jira_project_mappings', function (Blueprint $table) {
            $table->dropColumn('is_mapped_per_epic');
        });
    }
};
