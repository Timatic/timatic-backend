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
        Schema::table('entries', function (Blueprint $table) {
            $table->string('created_by_user_full_name')->nullable()->after('user_full_name');
            $table->string('created_by_user_email')->nullable()->after('user_full_name');
            $table->string('created_by_user_id')->nullable()->after('user_full_name');

            $table->index('created_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropIndex('entries_created_by_user_id_index');

            $table->dropColumn('created_by_user_id');
            $table->dropColumn('created_by_user_email');
            $table->dropColumn('created_by_user_full_name');
        });
    }
};
