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
            update customers
            set customers.service_manager_user_id = (
                select users.id from users where users.external_id = customers.service_manager_user_id
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            update customers
            set customers.service_manager_user_id = (
                select users.external_id from users where users.id = customers.service_manager_user_id
            )
        ');
    }
};
