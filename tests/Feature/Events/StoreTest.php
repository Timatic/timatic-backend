<?php

use App\Models\Budget;
use App\Models\Customer;
use App\Models\Event as TimeEvent;
use App\Models\EventType;
use App\Models\Integration;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Models\ProjectMapping;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('stores an event', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $source = Source::factory()->create();

    assert($source instanceof Source);

    /** @var User $user */
    $user = User::factory()->create();

    EventType::factory()->create(['id' => 'ticket_saved']);

    $response = $this->postJson(route('events.store'), eventRequestBody([
        'source_id' => $sourceId = $source->id,
        'user_id' => $user->id,
        'ticket_id' => $ticketId = $this->faker->uuid(),
        'event_type_id' => $eventTypeId = 'ticket_saved',
        'is_internal' => $isInternal = $this->faker->boolean(),
    ]))
        ->assertCreated();

    $attributes = $response->json()['data']['attributes'];

    expect($attributes['sourceId'])->toEqual($sourceId);
    expect($attributes['userId'])->toEqual($user->id);
    expect($attributes['ticketId'])->toEqual($ticketId);
    expect($attributes['eventTypeId'])->toEqual($eventTypeId);
    expect($attributes['isInternal'])->toEqual($isInternal);
});

it('stores an event and finds a customer by its external id', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $this->postJson(route('events.store'), eventRequestBody([
        'user_id' => User::factory(),
        'customer_external_id' => $customer->external_id,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.attributes.customerId', $customer->id);
});

it('stores an event and ignores a non existing customer', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    $this->postJson(route('events.store'), eventRequestBody([
        'user_id' => User::factory(),
        'customer_external_id' => 'fake',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.attributes.customerId', null);
});

it('stores an event with a description', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    $description = $this->faker->sentence();

    $this->postJson(route('events.store'), eventRequestBody([
        'user_id' => User::factory(),
        'description' => $description,
    ]))->assertCreated()
        ->assertJsonPath('data.attributes.description', $description);

    $this->assertDatabaseHas('events', ['description' => $description]);
});

it('stores an event and finds a user by its external id', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    /** @var User $user */
    $user = User::factory()->create();

    $response = $this->postJson(route('events.store'), eventRequestBody([
        'user_external_id' => $user->external_id,
    ]))->assertCreated();

    expect($response->json('data.attributes.userId'))->toEqual($user->id);
});

/**
 * @return array<string, string>
 */
function eventRequestBody(array $attributes): array
{
    if (! array_key_exists('event_type_id', $attributes)) {
        $attributes['event_type_id'] = EventType::factory()->create()->id;
    }

    $event = TimeEvent::factory()->make($attributes);

    $eventAttributes = (new Collection($event->toArray()))
        ->mapWithKeys(function (mixed $item, string $key) {
            return [Str::camel($key) => $item];
        });

    return [
        'data' => [
            'type' => 'events',
            'attributes' => $eventAttributes,
        ],
    ];
}

it('refuses an event that ends in the future', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    $source = Source::factory()->create();

    $eventType = EventType::factory()->create();

    /** @var User $user */
    $user = User::factory()->create();

    $this->postJson(route('events.store'), [
        'data' => [
            'type' => 'events',
            'attributes' => [
                'sourceId' => $source->id,
                'eventTypeId' => $eventType->id,
                'userId' => $user->id,
                'endedAt' => Carbon::now()->addHour()->toIso8601String(),
            ],
        ],
    ])->assertJsonValidationErrorFor('data.attributes.endedAt');
});

it('refuses an event that ends before it starts', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    $source = Source::factory()->create();

    $eventType = EventType::factory()->create();

    /** @var User $user */
    $user = User::factory()->create();

    $this->postJson(route('events.store'), [
        'data' => [
            'type' => 'events',
            'attributes' => [
                'sourceId' => $source->id,
                'eventTypeId' => $eventType->id,
                'userId' => $user->id,
                'startedAt' => Carbon::now()->subHour()->toIso8601String(),
                'endedAt' => Carbon::now()->subHours(2)->toIso8601String(),
            ],
        ],
    ])->assertJsonValidationErrorFor('data.attributes.endedAt');
});

it('refuses an event that spans more than a day', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    $source = Source::factory()->create();

    $eventType = EventType::factory()->create();

    /** @var User $user */
    $user = User::factory()->create();

    $this->postJson(route('events.store'), [
        'data' => [
            'type' => 'events',
            'attributes' => [
                'sourceId' => $source->id,
                'eventTypeId' => $eventType->id,
                'userId' => $user->id,
                'startedAt' => Carbon::now()->subHours(30)->toIso8601String(),
                'endedAt' => Carbon::now()->subMinutes(5)->toIso8601String(),
            ],
        ],
    ])->assertJsonValidationErrorFor('data.attributes.endedAt');
});

it('stores an event from the Claude Code plugin', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    /** @var User $user */
    $user = User::factory()->create();

    $this->travelTo(Carbon::parse('2026-10-15 10:07:00', 'UTC'));

    $this->postJson(route('events.store'), [
        'data' => [
            'type' => 'events',
            'attributes' => [
                'sourceId' => 'claude_code',
                'eventTypeId' => 'claude_question_answered',
                'userId' => $user->id,
                'customerId' => $customer->id,
                'ticketNumber' => 'TIM-12',
                'title' => 'Claude Code: timatic-backend',
                'description' => 'Login form',
                'startedAt' => '2026-10-15T10:00:00.000Z',
                'endedAt' => '2026-10-15T10:07:00.000Z',
            ],
        ],
    ])->assertCreated();

    /** @var TimeEvent $event */
    $event = TimeEvent::query()->sole();

    expect($event->eventType?->weight)->toBe(20)
        ->and($event->started_at?->toDateTimeString())->toBe('2026-10-15 10:00:00')
        ->and($event->ended_at->toDateTimeString())->toBe('2026-10-15 10:07:00');
});

/**
 * @return array<string, mixed>
 */
function claudeCodeEvent(array $attributes): array
{
    return [
        'data' => [
            'type' => 'events',
            'attributes' => [
                'sourceId' => 'claude_code',
                'eventTypeId' => 'claude_prompt_submitted',
                'userId' => User::factory()->create()->id,
                'endedAt' => Carbon::now()->subMinute()->toIso8601String(),
                ...$attributes,
            ],
        ],
    ];
}

function mapJiraProject(string $projectKey, Customer $customer, ?Budget $budget): void
{
    $integration = Integration::factory()->create(['type' => 'jira']);

    ProjectMapping::query()->create([
        'integration_id' => $integration->id,
        'project_key' => $projectKey,
        'project_name' => $projectKey,
        'customer_id' => $customer->id,
        'budget_id' => $budget?->id,
        'is_archived' => false,
    ]);
}

it('books an event on the customer and budget its ticket is mapped to', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    /** @var Budget $mappedBudget */
    $mappedBudget = Budget::factory()->create();
    mapJiraProject('ACME', $mappedBudget->customer, $mappedBudget);

    /** @var Budget $sentBudget */
    $sentBudget = Budget::factory()->create();

    $this->postJson(route('events.store'), claudeCodeEvent([
        'ticketNumber' => 'ACME-12',
        'customerId' => $sentBudget->customer_id,
        'budgetId' => $sentBudget->id,
    ]))->assertCreated();

    $event = TimeEvent::query()->sole();

    expect((int) $event->customer_id)->toBe($mappedBudget->customer_id)
        ->and($event->budget_id)->toBe($mappedBudget->id);
});

it('drops a budget of another customer than the ticket is mapped to', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    /** @var Customer $mappedCustomer */
    $mappedCustomer = Customer::factory()->create();
    mapJiraProject('ACME', $mappedCustomer, null);

    /** @var Budget $sentBudget */
    $sentBudget = Budget::factory()->create();

    $this->postJson(route('events.store'), claudeCodeEvent([
        'ticketNumber' => 'ACME-12',
        'customerId' => $sentBudget->customer_id,
        'budgetId' => $sentBudget->id,
    ]))->assertCreated();

    $event = TimeEvent::query()->sole();

    expect((int) $event->customer_id)->toBe($mappedCustomer->id)
        ->and($event->budget_id)->toBeNull();
});

it('keeps the budget sent when it belongs to the customer the ticket is mapped to', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    /** @var Budget $sentBudget */
    $sentBudget = Budget::factory()->create();
    mapJiraProject('ACME', $sentBudget->customer, null);

    $this->postJson(route('events.store'), claudeCodeEvent([
        'ticketNumber' => 'ACME-12',
        'customerId' => $sentBudget->customer_id,
        'budgetId' => $sentBudget->id,
    ]))->assertCreated();

    expect(TimeEvent::query()->sole()->budget_id)->toBe($sentBudget->id);
});

it('keeps what the client sent for a ticket that is not mapped', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    mapJiraProject('ACME', Customer::factory()->create(), null);

    /** @var Budget $sentBudget */
    $sentBudget = Budget::factory()->create();

    $this->postJson(route('events.store'), claudeCodeEvent([
        'ticketNumber' => 'OTHER-12',
        'customerId' => $sentBudget->customer_id,
        'budgetId' => $sentBudget->id,
    ]))->assertCreated();

    $event = TimeEvent::query()->sole();

    expect((int) $event->customer_id)->toBe($sentBudget->customer_id)
        ->and($event->budget_id)->toBe($sentBudget->id);
});

it('ignores the mapping of an archived project', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    mapJiraProject('ACME', Customer::factory()->create(), null);
    ProjectMapping::query()->update(['is_archived' => true]);

    /** @var Customer $sentCustomer */
    $sentCustomer = Customer::factory()->create();

    $this->postJson(route('events.store'), claudeCodeEvent([
        'ticketNumber' => 'ACME-12',
        'customerId' => $sentCustomer->id,
    ]))->assertCreated();

    expect((int) TimeEvent::query()->sole()->customer_id)->toBe($sentCustomer->id);
});
