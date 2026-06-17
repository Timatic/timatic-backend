<?php

use App\Models\Customer;
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
