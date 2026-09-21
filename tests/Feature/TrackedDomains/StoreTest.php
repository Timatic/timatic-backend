<?php

use App\Models\Budget;
use App\Models\Customer;
use App\Models\TrackedDomain;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

it('stores a tracked domain from a full url', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $budget = Budget::factory()->create();

    $response = $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'domain' => 'https://WWW.Jira.Acme.com/browse/TIM-1',
                'customerId' => $budget->customer_id,
                'budgetId' => $budget->id,
            ],
        ],
    ])->assertCreated();

    /** @var TrackedDomain $trackedDomain */
    $trackedDomain = TrackedDomain::query()->sole();

    expect($trackedDomain->domain)->toEqual('jira.acme.com');
    expect($trackedDomain->customer_id)->toEqual($budget->customer_id);
    expect($trackedDomain->budget_id)->toEqual($budget->id);
    expect($trackedDomain->created_by_user_id)->toEqual($user->id);
    expect($response->json('data.attributes.isActive'))->toBeTrue();
});

it('stores the path of a url as a separate mapping', function () {
    $this->loginUser(permissions: ['tracked-domains.create']);

    $budget = Budget::factory()->create();

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'domain' => 'https://gitlab.acme.com/group-a/project/-/issues',
                'path' => '/group-a',
                'customerId' => $budget->customer_id,
                'budgetId' => $budget->id,
            ],
        ],
    ])->assertCreated();

    /** @var TrackedDomain $trackedDomain */
    $trackedDomain = TrackedDomain::query()->sole();

    expect($trackedDomain->domain)->toEqual('gitlab.acme.com');
    expect($trackedDomain->path)->toEqual('/group-a');
});

it('takes the path from the url when none is given', function () {
    $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'domain' => 'https://gitlab.acme.com/group-a/',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertCreated();

    expect(TrackedDomain::query()->sole()->path)->toEqual('/group-a');
});

it('allows the same domain with a different path', function () {
    $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();

    TrackedDomain::factory()->create(['domain' => 'gitlab.acme.com', 'path' => '/group-a']);

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'domain' => 'gitlab.acme.com',
                'path' => '/group-b',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertCreated();

    expect(TrackedDomain::query()->count())->toEqual(2);
});

it('refuses the same domain and path twice', function () {
    $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();

    TrackedDomain::factory()->create(['domain' => 'gitlab.acme.com', 'path' => '/group-a']);

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'domain' => 'https://gitlab.acme.com/group-a',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertJsonValidationErrors('data.attributes.domain');
});

it('refuses a budget that belongs to another customer', function () {
    $this->loginUser(permissions: ['tracked-domains.create']);

    $budget = Budget::factory()->create();
    $otherCustomer = Customer::factory()->create();

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'domain' => 'jira.acme.com',
                'customerId' => $otherCustomer->id,
                'budgetId' => $budget->id,
            ],
        ],
    ])->assertJsonValidationErrors('data.attributes.budgetId');
});

it('refuses a domain that is already tracked', function () {
    $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();
    TrackedDomain::factory()->create(['domain' => 'jira.acme.com']);

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'domain' => 'https://jira.acme.com',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertJsonValidationErrors('data.attributes.domain');
});

it('refuses a user without the create permission', function () {
    $this->loginUser();

    $customer = Customer::factory()->create();

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'domain' => 'jira.acme.com',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertForbidden();
});

it('pauses a tracked domain', function () {
    $this->loginUser(permissions: ['tracked-domains.update']);

    $trackedDomain = TrackedDomain::factory()->create(['domain' => 'jira.acme.com']);

    $this->patchJson(route('tracked-domains.update', $trackedDomain), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => ['isActive' => false],
        ],
    ])->assertOk();

    expect($trackedDomain->refresh()->is_active)->toBeFalse();
});

it('removes a tracked domain', function () {
    $this->loginUser(permissions: ['tracked-domains.delete']);

    $trackedDomain = TrackedDomain::factory()->create();

    $this->deleteJson(route('tracked-domains.destroy', $trackedDomain))->assertNoContent();

    expect(TrackedDomain::query()->count())->toEqual(0);
});
