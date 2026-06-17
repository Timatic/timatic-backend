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
            update events
            set events.user_id = (
                select users.id from users where users.external_id = events.user_id
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            update events
            set events.user_id = (
                select users.external_id from users where users.id = events.user_id
            )
        ');
    }
};
