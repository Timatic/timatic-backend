<?php

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('returns a customer with account manager relation loaded', function () {
    $this->loginUser(permissions: ['customers.read']);

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $response = $this->get('customers/'.$customer->id.'?include=accountManager')->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
        ]);

    expect($response->json('data.attributes.hourlyRate'))->toEqual($customer->hourly_rate);

    expect($response->json('data.relationships.accountManager.data.id'))->toEqual((string) $customer->account_manager_user_id);
});
