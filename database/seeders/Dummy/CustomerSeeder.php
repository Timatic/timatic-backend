<?php

namespace Database\Seeders\Dummy;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Faker\Factory;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Factory::create('nl_NL');

        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Creating customers without account managers.');
        }

        $serviceManagementTeam = Team::firstWhere('name', 'service management');
        $serviceManagers = $serviceManagementTeam !== null ? $serviceManagementTeam->users : collect();

        for ($i = 0; $i < 30; $i++) {
            $serviceManagerId = null;
            if ($faker->boolean(70) && $serviceManagers->isNotEmpty()) {
                $serviceManagerId = $serviceManagers->random()->id;
            }

            Customer::create([
                'external_id' => $faker->randomNumber(5, true),
                'name' => $faker->company(),
                'hourly_rate' => number_format($faker->randomFloat(2, 50, 150), 2),
                'account_manager_user_id' => $serviceManagerId,
            ]);
        }
    }
}
