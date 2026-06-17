<?php

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('cannot delete a customer', function () {
    test()->loginUser(['user']);

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $this->delete('customers/'.$customer->id)->assertForbidden();
});
