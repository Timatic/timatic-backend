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
            set entries.user_id = (
                select users.id from users where users.external_id = entries.user_id
            ), entries.created_by_user_id = (
                select users.id from users where users.external_id = entries.created_by_user_id
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            update entries
            set entries.user_id = (
                select users.external_id from users where users.id = entries.user_id
            ), entries.created_by_user_id = (
                select users.external_id from users where users.id = entries.created_by_user_id
            )
        ');
    }
};
