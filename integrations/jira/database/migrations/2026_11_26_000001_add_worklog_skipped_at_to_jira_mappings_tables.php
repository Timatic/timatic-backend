<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jira_user_mappings', function (Blueprint $table) {
            $table->timestamp('worklog_skipped_at')->nullable()->after('user_id');
        });

        Schema::table('jira_project_mappings', function (Blueprint $table) {
            $table->timestamp('worklog_skipped_at')->nullable()->after('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('jira_user_mappings', function (Blueprint $table) {
            $table->dropColumn('worklog_skipped_at');
        });

        Schema::table('jira_project_mappings', function (Blueprint $table) {
            $table->dropColumn('worklog_skipped_at');
        });
    }
};
