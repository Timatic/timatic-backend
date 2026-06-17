<?php

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('puts a customer', function () {
    $this->markTestSkipped('No customer updates supported for now');

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
    $this->markTestSkipped('No customer updates supported for now');

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
