<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            update entries
            set created_by_user_id = user_id,
                created_by_user_email = user_email,
                created_by_user_full_name = user_full_name
            where deleted_at is null
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            update entries
            set created_by_user_id = null,
                created_by_user_email = null,
                created_by_user_full_name = null
            where deleted_at is null
        ');
    }
};
