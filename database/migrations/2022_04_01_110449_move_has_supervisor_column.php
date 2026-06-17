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
            $table->dropColumn('has_supervisor');
        });
        Schema::table('budget_types', function (Blueprint $table) {
            $table->boolean('has_supervisor')->default(false)->after('renewal_frequencies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
