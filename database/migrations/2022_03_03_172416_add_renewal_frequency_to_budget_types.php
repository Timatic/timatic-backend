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
            $table->json('renewal_frequencies');
            $table->dropColumn('has_renewal_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_types', function (Blueprint $table) {
            $table->boolean('has_renewal_frequency')->after('has_start_and_end_date');
            $table->dropColumn('renewal_frequencies');
        });
    }
};
