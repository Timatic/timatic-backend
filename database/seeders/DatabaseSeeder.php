<?php

namespace Database\Seeders;

use Database\Seeders\Dummy\BudgetTypeSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(OvertimeTypeSeeder::class);
        $this->call(OvertimeRuleSeeder::class);
        $this->call(BudgetTypeSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(RoleSeeder::class);
    }
}
