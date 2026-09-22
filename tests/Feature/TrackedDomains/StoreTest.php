<?php

use App\Models\Budget;
use App\Models\Customer;
use App\Models\TrackedDomain;
use App\Models\User;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

it('stores a tracked domain from a full url', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $budget = Budget::factory()->create();

    $response = $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
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
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $budget = Budget::factory()->create();

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
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
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
                'domain' => 'https://gitlab.acme.com/group-a/',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertCreated();

    expect(TrackedDomain::query()->sole()->path)->toEqual('/group-a');
});

it('allows the same domain with a different path', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();

    TrackedDomain::factory()->create(['domain' => 'gitlab.acme.com', 'path' => '/group-a']);

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
                'domain' => 'gitlab.acme.com',
                'path' => '/group-b',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertCreated();

    expect(TrackedDomain::query()->count())->toEqual(2);
});

it('refuses the same domain and path twice', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();

    TrackedDomain::factory()->create(['domain' => 'gitlab.acme.com', 'path' => '/group-a', 'user_id' => $user->id]);

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
                'domain' => 'https://gitlab.acme.com/group-a',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertJsonValidationErrors('data.attributes.domain');
});

it('refuses a budget that belongs to another customer', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $budget = Budget::factory()->create();
    $otherCustomer = Customer::factory()->create();

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
                'domain' => 'jira.acme.com',
                'customerId' => $otherCustomer->id,
                'budgetId' => $budget->id,
            ],
        ],
    ])->assertJsonValidationErrors('data.attributes.budgetId');
});

it('refuses a domain that is already tracked', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();
    TrackedDomain::factory()->create(['domain' => 'jira.acme.com', 'user_id' => $user->id]);

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
                'domain' => 'https://jira.acme.com',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertJsonValidationErrors('data.attributes.domain');
});

it('refuses a user without the create permission', function () {
    $user = $this->loginUser();

    $customer = Customer::factory()->create();

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
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

it('keeps a mapping to the user who made it', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
                'domain' => 'timatic-backend.test',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertCreated();

    expect(TrackedDomain::query()->sole()->user_id)->toEqual($user->id);
});

it('refuses a mapping without a user', function () {
    $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'domain' => 'jira.acme.com',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertJsonValidationErrors('data.attributes.userId');
});

it('refuses a mapping for somebody else', function () {
    $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => User::factory()->create()->id,
                'domain' => 'jira.acme.com',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertJsonValidationErrors('data.attributes.userId');
});

it('accepts a shared mapping as a mapping of its own', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $shared = TrackedDomain::factory()->create(['domain' => 'jira.acme.com']);

    $response = $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
                'sourceId' => $shared->id,
                'domain' => 'jira.acme.com',
                'customerId' => $shared->customer_id,
            ],
        ],
    ])->assertCreated();

    expect($response->json('data.attributes.sourceId'))->toEqual($shared->id);
    expect(TrackedDomain::query()->where('user_id', $user->id)->sole()->source_id)->toEqual($shared->id);
});

it('refuses to accept a mapping that belongs to somebody', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $other = TrackedDomain::factory()->create(['domain' => 'jira.acme.com', 'user_id' => User::factory()->create()->id]);

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
                'sourceId' => $other->id,
                'domain' => 'jira.acme.com',
                'customerId' => $other->customer_id,
            ],
        ],
    ])->assertJsonValidationErrors('data.attributes.sourceId');
});

it('lets two users map the same domain', function () {
    $user = $this->loginUser(permissions: ['tracked-domains.create']);

    $customer = Customer::factory()->create();
    TrackedDomain::factory()->create(['domain' => 'jira.acme.com', 'user_id' => User::factory()->create()->id]);

    $this->postJson(route('tracked-domains.store'), [
        'data' => [
            'type' => 'tracked-domains',
            'attributes' => [
                'userId' => $user->id,
                'domain' => 'jira.acme.com',
                'customerId' => $customer->id,
            ],
        ],
    ])->assertCreated();

    expect(TrackedDomain::query()->count())->toEqual(2);
});

it('lets its owner remove a private mapping without the delete permission', function () {
    $user = $this->loginUser();

    $trackedDomain = TrackedDomain::factory()->create(['user_id' => $user->id]);

    $this->deleteJson(route('tracked-domains.destroy', $trackedDomain))->assertNoContent();
});

it('refuses to remove a private mapping of somebody else', function () {
    $this->loginUser(permissions: ['tracked-domains.create']);

    $trackedDomain = TrackedDomain::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->deleteJson(route('tracked-domains.destroy', $trackedDomain))->assertForbidden();
});
