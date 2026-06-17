<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('cannot store a customer', function () {
    test()->loginUser(['user']);

    $this->postJson('customers', [
        'type' => 'customers',
        'attributes' => [
            'name' => $name = $this->faker->company(),
            'externalId' => $externalId = $this->faker->uuid(),
        ],
    ])->assertForbidden();
});
