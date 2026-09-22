<?php

use App\Models\Budget;
use App\Models\Customer;
use App\Models\TrackedDomain;
use App\Models\User;
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

it('hides a private mapping of another user', function () {
    $this->loginUser(permissions: ['tracked-domains.read']);

    TrackedDomain::factory()->create(['domain' => 'shared.acme.com']);
    TrackedDomain::factory()->create(['domain' => 'someone.test', 'user_id' => User::factory()->create()->id]);

    $response = $this->getJson(route('tracked-domains.index'))->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.attributes.domain'))->toEqual('shared.acme.com');
});

it('shows a user their own private mapping', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.read']);

    TrackedDomain::factory()->create(['domain' => 'timatic-backend.test', 'user_id' => $user->id]);

    $response = $this->getJson(route('tracked-domains.index'))->assertOk();

    expect($response->json('data.0.attributes.userId'))->toEqual($user->id);
});

it('filters the mappings shared with everyone', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.read']);

    TrackedDomain::factory()->create(['domain' => 'shared.acme.com']);
    TrackedDomain::factory()->create(['domain' => 'timatic-backend.test', 'user_id' => $user->id]);

    $response = $this->getJson(route('tracked-domains.index', ['filter' => ['shared' => 'true']]))->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.attributes.domain'))->toEqual('shared.acme.com');
    expect($response->json('data.0.attributes.userId'))->toBeNull();
});

it('filters the mappings of one user', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.read']);

    TrackedDomain::factory()->create(['domain' => 'shared.acme.com']);
    TrackedDomain::factory()->create(['domain' => 'timatic-backend.test', 'user_id' => $user->id]);

    $response = $this->getJson(route('tracked-domains.index', ['filter' => ['userId' => $user->id]]))->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.attributes.domain'))->toEqual('timatic-backend.test');
});
