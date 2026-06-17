<?php

use App\Models\Activity;
use App\Models\ApiToken;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\EntrySuggestion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\LoginUser;
use TiMacDonald\JsonApi\JsonApiResource;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

beforeEach(function () {
    Event::fake();
});

it('stores an entry created by another user with permission', function () {
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $this->loginUser(permissions: ['user', 'entries.create_for_others']);

    $this->postJson('entries', [
        'data' => [
            'type' => 'entries',
            'attributes' => [
                'isPaidPerHour' => false,
                'ticketId' => $this->faker->uuid(),
                'customerId' => $customer->id,
                'userFullName' => $this->faker->name(),
                'startedAt' => Carbon::now()->subDay(),
                'endedAt' => Carbon::now()->subDay()->addHour(),
                'entryType' => 'regular',
                'isInternal' => true,
                'hasOvertime' => false,
                'hasCustomerOvertime' => false,
            ],
        ],
    ])->assertCreated();
});

it('does not store an entry created by another user without permission', function () {
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $this->loginUser();

    $this->postJson('entries', [
        'data' => [
            'type' => 'entries',
            'attributes' => [
                'isPaidPerHour' => false,
                'ticketId' => $this->faker->uuid(),
                'customerId' => $customer->id,
                'userFullName' => $this->faker->name(),
                'startedAt' => Carbon::now()->subDay(),
                'endedAt' => Carbon::now()->subDay()->addHour(),
                'entryType' => 'regular',
                'isInternal' => true,
                'hasOvertime' => false,
                'hasCustomerOvertime' => false,
            ],
        ],
    ])->assertForbidden();
});

it('stores an entry with a suggestion id', function () {
    $this->loginUser(permissions: ['user', 'entries.create_for_others']);

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    /** @var User $user */
    $user = $this->loginUser();

    /** @var EntrySuggestion $entrySuggestion */
    $entrySuggestion = EntrySuggestion::factory()
        ->has(Activity::factory()->state([
            'started_at' => Carbon::now(),
            'ended_at' => Carbon::now()->addHour(),
        ]))
        ->create([
            'ticket_id' => $this->faker->uuid(),
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'date' => $this->faker->date(),
        ]);

    /** @var Activity $activity */
    $activity = $entrySuggestion->activities->first();

    $response = $this->postJson('entries', [
        'type' => 'entries',
        'attributes' => [
            'isPaidPerHour' => false,
            'entrySuggestionId' => $entrySuggestion->id,
            'ticketId' => $entrySuggestion->ticket_id,
            'customerId' => (string) $entrySuggestion->customer_id,
            'userId' => $entrySuggestion->user_id,
            'startedAt' => $activity->started_at,
            'endedAt' => $activity->ended_at,
            'entryType' => 'regular',
            'isInternal' => true,
            'hasOvertime' => false,
            'hasCustomerOvertime' => false,
        ],
    ])->assertCreated();

    /** @var Entry $entry */
    $entry = Entry::query()->find($response['data']['id']);

    expect($entry->entry_suggestion_id)->toEqual($entrySuggestion->id);

    expect(JsonApiResource::$wrap)->toEqual('data');
});

test('an entry can be created on the first of the month with proper permissions', function () {
    $this->travelTo(Carbon::today()->endOfMonth());

    /** @var User $user */
    $user = $this->loginUser(['email' => $this->faker->companyEmail()], ['user', 'entries.update_from_previous_month']);

    /** @var Entry $entry */
    $entry = Entry::factory()->make([
        'user_id' => $user->id,
        'started_at' => Carbon::now()->startOfMonth(),
        'ended_at' => Carbon::now()->startOfMonth()->addHour(),
    ]);

    $this->postJson('entries', [
        'type' => 'entries',
        'attributes' => [
            'hasOvertime' => false,
            'hasCustomerOvertime' => false,
            'entryType' => $entry->entry_type,
            'customerId' => $entry->customer_id,
            'userId' => $entry->user_id,
            'startedAt' => $entry->started_at,
            'endedAt' => $entry->ended_at,
            'isInternal' => true,
        ],
    ])->assertCreated();
});

it('does not store an entry when the budget has not started', function () {
    /** @var User $user */
    $user = $this->loginUser(['email' => $this->faker->companyEmail()]);

    /** @var Budget $budget */
    $budget = Budget::factory()->create([
        'started_at' => Carbon::now()->addWeek(),
        'ended_at' => Carbon::now()->addYear(),
    ]);

    BudgetVersion::factory()->create([
        'budget_id' => $budget->id,
        'effective_from' => $budget->started_at,
    ]);

    /** @var Entry $entry */
    $entry = Entry::factory()->make([
        'budget_id' => $budget->id,
        'user_id' => $user->id,
        'started_at' => Carbon::now()->subDay(),
        'ended_at' => Carbon::now()->subDay()->addHour(),
    ]);

    $this->postJson('entries', [
        'data' => [
            'type' => 'entries',
            'attributes' => [
                'customerId' => $entry->customer_id,
                'entryType' => $entry->entry_type,
                'hasOvertime' => false,
                'hasCustomerOvertime' => false,
                'budgetId' => $entry->budget_id,
                'userId' => $entry->user_id,
                'startedAt' => $entry->started_at,
                'endedAt' => $entry->ended_at,
            ],
        ],
    ])->assertStatus(422)
        ->assertJson([
            'errors' => [
                'data.attributes.budgetId' => ['This budget isn\'t available at the selected date and time.'],
            ],
        ]);
});

it('stores an entry if the current user is an allowed user on the budget', function () {
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    /** @var Budget $budget */
    $budget = Budget::factory()->create(['customer_id' => $customer->id]);

    BudgetVersion::factory()->create(['budget_id' => $budget->id]);

    /** @var User $user */
    $user = $this->loginUser(permissions: ['user', 'entries.create']);

    $budget->allowedUsers()->sync([$user->id]);

    $this->postJson('entries', [
        'data' => [
            'type' => 'entries',
            'attributes' => [
                'isPaidPerHour' => false,
                'ticketId' => $this->faker->uuid(),
                'budgetId' => $budget->id,
                'customerId' => $customer->id,
                'userFullName' => $user->full_name,
                'startedAt' => Carbon::now()->subDay(),
                'endedAt' => Carbon::now()->subDay()->addHour(),
                'entryType' => 'regular',
                'userId' => $user->id,
                'isInternal' => false,
                'hasOvertime' => false,
                'hasCustomerOvertime' => false,
            ],
        ],
    ])->assertCreated();
});

it('does not store an entry if the current user is not an allowed user on the budget', function () {
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    /** @var Budget $budget */
    $budget = Budget::factory()->create(['customer_id' => $customer->id]);

    BudgetVersion::factory()->create(['budget_id' => $budget->id]);

    /** @var User $user */
    $user = $this->loginUser(permissions: ['user', 'entries.create']);

    $budget->allowedUsers()->sync([User::factory()->create()->id]);

    $this->postJson('entries', [
        'data' => [
            'type' => 'entries',
            'attributes' => [
                'isPaidPerHour' => false,
                'ticketId' => $this->faker->uuid(),
                'budgetId' => $budget->id,
                'customerId' => $customer->id,
                'userFullName' => $user->full_name,
                'startedAt' => Carbon::now()->subDay(),
                'endedAt' => Carbon::now()->subDay()->addHour(),
                'entryType' => 'regular',
                'userId' => $user->id,
                'isInternal' => false,
                'hasOvertime' => false,
                'hasCustomerOvertime' => false,
            ],
        ],
    ])->assertForbidden();
});

it('stores an entry if the budget has no allowed users', function () {
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    /** @var Budget $budget */
    $budget = Budget::factory()->create(['customer_id' => $customer->id]);

    BudgetVersion::factory()->create(['budget_id' => $budget->id]);

    /** @var User $user */
    $user = $this->loginUser(permissions: ['user', 'entries.create']);

    $budget->allowedUsers()->sync([]);

    $this->postJson('entries', [
        'data' => [
            'type' => 'entries',
            'attributes' => [
                'isPaidPerHour' => false,
                'ticketId' => $this->faker->uuid(),
                'budgetId' => $budget->id,
                'customerId' => $customer->id,
                'userFullName' => $user->full_name,
                'startedAt' => Carbon::now()->subDay(),
                'endedAt' => Carbon::now()->subDay()->addHour(),
                'entryType' => 'regular',
                'userId' => $user->id,
                'isInternal' => false,
                'hasOvertime' => false,
                'hasCustomerOvertime' => false,
            ],
        ],
    ])->assertSuccessful();
});

it('stores an entry created by an api token', function () {
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    /** @var ApiToken $token */
    $token = ApiToken::factory()->create();

    $token->givePermissionTo(['user', 'entries.create_for_others']);

    Auth::setUser($token);

    $response = $this->postJson('entries', [
        'data' => [
            'type' => 'entries',
            'attributes' => [
                'isPaidPerHour' => false,
                'ticketId' => $this->faker->uuid(),
                'customerId' => $customer->id,
                'userFullName' => $this->faker->name(),
                'startedAt' => Carbon::now()->subDay(),
                'endedAt' => Carbon::now()->subDay()->addHour(),
                'entryType' => 'regular',
                'isInternal' => true,
                'hasOvertime' => false,
                'hasCustomerOvertime' => false,
            ],
        ],
    ])->assertSuccessful();
});
