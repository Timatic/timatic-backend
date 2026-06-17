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
            delete from entry_suggestions where user_id not in (select users.external_id from users)
        ');

        DB::statement('
            update entry_suggestions
            set entry_suggestions.user_id = (
                select users.id from users where users.external_id = entry_suggestions.user_id
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            update entry_suggestions
            set entry_suggestions.user_id = (
                select users.external_id from users where users.id = entry_suggestions.user_id
            )
        ');
    }
};
