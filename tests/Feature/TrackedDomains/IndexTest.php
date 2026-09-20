<?php

use App\Models\Budget;
use App\Models\Customer;
use App\Models\TrackedDomain;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

it('lists the tracked domains with their customer', function () {
    $this->loginUser(permissions: ['tracked-domains.read']);

    $customer = Customer::factory()->create(['name' => 'Acme']);
    TrackedDomain::factory()->create(['domain' => 'jira.acme.com', 'customer_id' => $customer->id]);

    $response = $this->getJson(route('tracked-domains.index', ['include' => 'customer']))->assertOk();

    expect($response->json('data.0.attributes.domain'))->toEqual('jira.acme.com');
    expect($response->json('included.0.attributes.name'))->toEqual('Acme');
});

it('filters the tracked domains by customer', function () {
    $this->loginUser(permissions: ['tracked-domains.read']);

    $customer = Customer::factory()->create();
    TrackedDomain::factory()->create(['domain' => 'jira.acme.com', 'customer_id' => $customer->id]);
    TrackedDomain::factory()->create(['domain' => 'gitlab.other.com']);

    $response = $this->getJson(route('tracked-domains.index', ['filter' => ['customerId' => $customer->id]]))->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.attributes.domain'))->toEqual('jira.acme.com');
});

it('refuses a user without the read permission', function () {
    $this->loginUser();

    $this->getJson(route('tracked-domains.index'))->assertForbidden();
});

it('exposes the budget of a tracked domain', function () {
    $this->loginUser(permissions: ['tracked-domains.read']);

    $budget = Budget::factory()->create();

    TrackedDomain::factory()->create([
        'domain' => 'jira.acme.com',
        'customer_id' => $budget->customer_id,
        'budget_id' => $budget->id,
    ]);

    $response = $this->getJson(route('tracked-domains.index'))->assertOk();

    expect($response->json('data.0.attributes.budgetId'))->toEqual($budget->id);
});
