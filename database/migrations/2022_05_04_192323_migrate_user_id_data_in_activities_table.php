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
            delete from activities where user_id not in (select users.external_id from users)
        ');

        DB::statement('
            update activities
            set activities.user_id = (
                select users.id from users where users.external_id = activities.user_id
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            update activities
            set activities.user_id = (
                select users.external_id from users where users.id = activities.user_id
            )
        ');
    }
};
