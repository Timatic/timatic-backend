<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budget_types', function (Blueprint $table) {
            $table->boolean('has_change_ticket')->default(false)->change();
            $table->dropColumn('has_start_and_end_date');
            $table->boolean('has_contract_id')->default(true)->after('has_supervisor');
            $table->boolean('has_total_price')->default(true)->after('has_supervisor');
            $table->string('default_title')->nullable()->after('has_supervisor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_types', function (Blueprint $table) {
            $table->dropColumn('has_contract_id');
            $table->dropColumn('has_total_price');
            $table->dropColumn('default_title');
            $table->boolean('has_start_and_end_date');
        });
    }
};
