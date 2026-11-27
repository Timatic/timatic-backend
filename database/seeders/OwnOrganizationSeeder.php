<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class OwnOrganizationSeeder extends Seeder
{
    // Internal hours are booked on this customer; an admin renames it after the install
    public function run(): void
    {
        Customer::query()->firstOrCreate(
            ['is_own_organization' => true],
            ['name' => config('app.name')],
        );
    }
}
