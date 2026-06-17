<?php

use App\Models\Budget;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jira_project_mappings', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jira_project_mappings', function (Blueprint $table) {
            $table->dropForeignIdFor(Budget::class);
        });
    }
};
