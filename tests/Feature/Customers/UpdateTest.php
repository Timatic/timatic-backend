<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('puts a customer', function () {
    $this->loginUser(permissions: ['customers.update']);

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $this->patchJson('customers/'.$customer->id, [
        'type' => 'customers',
        'attributes' => [
            'name' => $name = $this->faker->company(),
            'externalId' => $externalId = $this->faker->uuid(),
        ],
    ])->assertSuccessful()
        ->assertJson([
            'data' => [
                'type' => 'customers',
                'attributes' => [
                    'externalId' => $externalId,
                    'name' => $name,
                ],
            ],
        ]);

    $customer->refresh();

    expect($customer->external_id)->toEqual($externalId);
    expect($customer->name)->toEqual($name);
});

it('patches a customer', function () {
    $this->loginUser(permissions: ['customers.update']);

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $oldName = $customer->name;

    $this->patchJson('customers/'.$customer->id, [
        'type' => 'customers',
        'attributes' => [
            'externalId' => $externalId = $this->faker->uuid(),
        ],
    ])->assertSuccessful()
        ->assertJson([
            'data' => [
                'type' => 'customers',
                'attributes' => [
                    'externalId' => $externalId,
                    'name' => $oldName,
                ],
            ],
        ]);

    $customer->refresh();

    expect($customer->external_id)->toEqual($externalId);
    expect($customer->name)->toEqual($oldName);
});

it('sets the hourly rate, color and account manager of a customer', function () {
    $this->loginUser(permissions: ['customers.update']);

    $customer = Customer::factory()->create();
    $accountManager = User::factory()->create();

    $this->patchJson('customers/'.$customer->id, [
        'type' => 'customers',
        'attributes' => ['hourlyRate' => 95.5, 'color' => '3081b8'],
        'relationships' => [
            'accountManager' => ['data' => ['type' => 'users', 'id' => (string) $accountManager->id]],
        ],
    ])->assertSuccessful();

    expect($customer->refresh())
        ->hourly_rate->toEqual(95.5)
        ->color->toBe('3081b8')
        ->account_manager_user_id->toBe($accountManager->id);
});
