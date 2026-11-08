<?php

use App\Models\Customer;
use Database\Seeders\OwnOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the own organization once', function () {
    config(['app.name' => 'Pionect']);
    Customer::query()->delete();

    $this->seed(OwnOrganizationSeeder::class);
    $this->seed(OwnOrganizationSeeder::class);

    expect(Customer::query()->where('is_own_organization', true)->pluck('name')->all())->toBe(['Pionect']);
});
