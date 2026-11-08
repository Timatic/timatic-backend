<?php

use App\Models\ApiToken;
use App\Models\Customer;
use App\Models\Event as TimeEvent;
use App\Models\EventType;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Concerns\LoginUser;

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
        ->assertJsonPath('data.attributes.customerId', (string) $customer->id);
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

it('stores a google meet attendance posted by the chrome extension', function () {
    Event::fake();

    /** @var User $user */
    $user = User::factory()->create();
    $user->givePermissionTo('events.create');

    ApiToken::query()->create([
        'user_id' => $user->id,
        'client_id' => 'chrome-extension',
        'title' => 'Chrome op Mac',
        'key' => hash('sha512', 'extension-token'),
    ]);

    $this->postJson(route('events.store'), [
        'data' => [
            'type' => 'events',
            'attributes' => [
                'sourceId' => 'google_meet',
                'eventTypeId' => 'meeting_attended',
                'userId' => (string) $user->id,
                'externalId' => 'abc-defg-hij',
                'title' => 'Weekly sync',
                'startedAt' => '2026-11-02T09:00:00+01:00',
                'endedAt' => '2026-11-02T09:45:00+01:00',
            ],
        ],
    ], ['Authorization' => 'Bearer extension-token'])->assertCreated();

    $this->assertDatabaseHas('events', [
        'user_id' => $user->id,
        'source_id' => 'google_meet',
        'event_type_id' => 'meeting_attended',
        'external_id' => 'abc-defg-hij',
        'title' => 'Weekly sync',
    ]);
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
            $isId = in_array($key, ['user_id', 'customer_id'], true) && $item !== null;

            return [Str::camel($key) => $isId ? (string) $item : $item];
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
                'userId' => (string) $user->id,
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
                'userId' => (string) $user->id,
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
                'userId' => (string) $user->id,
                'startedAt' => Carbon::now()->subHours(30)->toIso8601String(),
                'endedAt' => Carbon::now()->subMinutes(5)->toIso8601String(),
            ],
        ],
    ])->assertJsonValidationErrorFor('data.attributes.endedAt');
});
