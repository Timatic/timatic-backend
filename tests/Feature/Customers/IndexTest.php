<?php

use App\Models\Customer;
use App\Models\TrackedDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('returns a collection response', function () {
    $this->loginUser(permissions: ['customers.read']);

    Customer::first()?->delete();
    $customers = Customer::factory()->count(10)->create();

    $response = $this->get('customers')->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
            'links' => [],
            'meta' => [],
        ])->assertJsonCount(10, 'data');

    expect($response->json('data.0.attributes.hourlyRate'))->toEqual($customers->first()->hourly_rate);
});

it('returns the pagination meta in camelCase', function () {
    $this->loginUser(permissions: ['customers.read']);

    $this->get('customers?page[size]=5')->assertSuccessful()
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonPath('meta.perPage', 5)
        ->assertJsonMissingPath('meta.current_page')
        ->assertJsonPath('links.prev', null);
});

it('rejects the count and exists variants of an include', function () {
    $this->loginUser(permissions: ['customers.read']);

    $this->getJson('customers?include=accountManagerCount')->assertBadRequest();
});

it('counts only the shared tracked domains of a customer', function () {
    $this->loginUser(permissions: ['customers.read']);

    Customer::query()->delete();
    $customer = Customer::factory()->create();
    TrackedDomain::factory()->for($customer)->create(['user_id' => null]);
    TrackedDomain::factory()->for($customer)->create(['user_id' => User::factory()]);

    $this->getJson('customers')
        ->assertSuccessful()
        ->assertJsonPath('data.0.attributes.trackedDomainsCount', 1);
});
