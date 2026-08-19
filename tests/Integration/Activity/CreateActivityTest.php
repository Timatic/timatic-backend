<?php

use App\Events\EventCreated;
use App\Jobs\RebuildUserDay;
use App\Models\Activity;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Source;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class);

uses(WithFaker::class);

test('if event has no start then activity should start 15 minutes before', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();

    /** @var Event $event */
    $event = Event::factory()->create([
        'user_id' => $user->id,
        'ended_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    $event->refresh()->load('activity');
    expect($event->activity()->exists())->toBeTrue();
    expect($event->activity?->events?->isNotEmpty())->toBeTrue();
    expect($event->ended_at->copy()->subMinutes(15))->toEqual($event->activity?->started_at);
});

test('if event has start and end then activity should be same period', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();

    /** @var Event $event */
    $event = Event::factory()->create([
        'user_id' => $user->id,
        'started_at' => Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    $event->refresh();
    expect($event->activity)->not->toBeNull();
    expect($event->activity->events->isNotEmpty())->toBeTrue();
    expect($event->started_at)->toEqual($event->activity->started_at);
    expect($event->ended_at)->toEqual($event->activity->ended_at);
});

test('created activity does not overlap existing one', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();

    /** @var Event $overlappingEvent */
    $overlappingEvent = Event::factory()->create([
        'user_id' => $user->id,
        'started_at' => Carbon::parse('2026-07-16 00:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:10', 'Europe/Amsterdam'),
    ]);

    /** @var Event $event */
    $event = Event::factory()->create([
        'user_id' => $user->id,
        'started_at' => Carbon::parse('2026-07-16 00:05', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:15', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect($overlappingEvent->fresh()->ended_at)->toBeGreaterThanOrEqual($event->fresh()->activity->started_at);
});

test('if two events overlap then one with highest weight should become activity', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $eventTypeLight = EventType::factory()->state(['weight' => 1])->create();
    $eventTypeHeavy = EventType::factory()->state(['weight' => 999])->create();

    $events = [];

    /** @var Event $overlappingEvent */
    $events[0] = Event::factory()->state([
        'user_id' => $user->id,
        'started_at' => Carbon::parse('2026-07-16 00:10', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:20', 'Europe/Amsterdam'),
        'event_type_id' => $eventTypeLight->id,
    ])->create();

    /** @var Event $event */
    $events[1] = Event::factory()->state([
        'user_id' => $user->id,
        'started_at' => Carbon::parse('2026-07-16 00:05', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:15', 'Europe/Amsterdam'),
        'event_type_id' => $eventTypeHeavy->id,
    ])->create();

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    // should return 2 activities
    expect($events[0]->fresh()->activity)->toBeInstanceOf(Activity::class);
    expect($events[1]->fresh()->activity)->toBeInstanceOf(Activity::class);

    // $events[1] should be the main activity because of its higher weight
    expect($events[1]->fresh()->activity->started_at)->toEqual($events[1]->started_at);
    expect($events[1]->fresh()->activity->ended_at)->toEqual($events[1]->ended_at);

    // $events[0] should start after $event[1] for the remainder of its duration that does NOT overlap
    expect($events[1]->ended_at)->toEqual($events[0]->fresh()->activity->started_at);
    expect($events[0]->fresh()->activity->ended_at)->toEqual($events[0]->ended_at);
});

test('if event fits in previous activity add it', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();

    Source::firstOrCreate(['id' => Source::ID_TOPDESK], ['title' => 'Topdesk']);

    $sameState = [
        'event_type_id' => EventType::factory()->create()->id,
        'customer_id' => $this->faker->word(),
        'ticket_number' => $this->faker->word(),
        'user_id' => $user->id,
        'source_id' => Source::ID_TOPDESK,
    ];

    /** @var Event $previousEvent */
    $previousEvent = Event::factory()->state(array_merge([
        'started_at' => Carbon::parse('2026-07-16 00:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:10', 'Europe/Amsterdam'),
    ], $sameState))->create();

    /** @var Event $event */
    $event = Event::factory()->state(array_merge([
        'started_at' => Carbon::parse('2026-07-16 00:15', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:20', 'Europe/Amsterdam'),
    ], $sameState))->create();

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    $activity = $event->fresh()->activity;
    expect($activity->events->pluck('id'))->toContain($previousEvent->id, $event->id);
    expect($activity->ended_at)->toEqual($event->ended_at);
});

test('activity should only contain events from one customer', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();

    /** @var Event[] $events */
    $events = [];

    $sameState = [
        'event_type_id' => EventType::factory()->createOne()->id,
        'ticket_number' => $this->faker->word(),
        'user_id' => $user->id,
    ];

    $events[0] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::parse('2026-07-16 00:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:05', 'Europe/Amsterdam'),
        'customer_id' => 'customerX',
    ]))->create();

    $events[1] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::parse('2026-07-16 00:10', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:15', 'Europe/Amsterdam'),
        'customer_id' => 'customerY',
    ]))->create();

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect($events[0]->fresh()->activity->customer_id)->toEqual($events[0]->customer_id);
    expect($events[1]->fresh()->activity->customer_id)->toEqual($events[1]->customer_id);
    $this->assertNotEquals($events[0]->fresh()->activity->customer_id, $events[1]->fresh()->activity->customer_id);
});

test('events without customer should not be combined', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();

    $events = [];
    $eventTypeId = EventType::factory()->createOne()->id;

    Source::firstOrCreate(['id' => Source::ID_OUTLOOK_CALENDAR], ['title' => 'Outlook Calendar']);

    $sameState = [
        'event_type_id' => $eventTypeId,
        'customer_id' => null,
        'ticket_number' => null,
        'source_id' => 'outlook_calendar',
        'user_id' => $user->id,
    ];

    $events[0] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::parse('2026-07-16 00:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:15', 'Europe/Amsterdam'),
    ]))->create();
    $events[1] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::parse('2026-07-16 00:15', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:20', 'Europe/Amsterdam'),
    ]))->create();

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect(Activity::query()->count())->toEqual(2);
});

test('events without ticket should not be combined', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();

    $events = [];
    $eventTypeId = EventType::factory()->createOne()->id;

    Source::firstOrCreate(['id' => Source::ID_OUTLOOK_CALENDAR], ['title' => 'Outlook Calendar']);

    $sameState = [
        'event_type_id' => $eventTypeId,
        'customer_id' => $this->faker->word(),
        'ticket_number' => null,
        'source_id' => 'outlook_calendar',
        'user_id' => $user->id,
    ];

    $events[0] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::parse('2026-07-16 00:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:15', 'Europe/Amsterdam'),
    ]))->create();
    $events[1] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::parse('2026-07-16 00:15', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 00:20', 'Europe/Amsterdam'),
    ]))->create();

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect(Activity::query()->count())->toEqual(1);
});

test('a fully covered lower-weight activity is absorbed instead of getting a negative duration', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);

    $eventTypeLight = EventType::factory()->state(['weight' => 1])->create();
    $eventTypeHeavy = EventType::factory()->state(['weight' => 999])->create();
    $user = User::factory()->create();

    /** @var Event $coveredEvent */
    $coveredEvent = Event::factory()->create([
        'user_id' => $user->id,
        'event_type_id' => $eventTypeLight->id,
        'started_at' => Carbon::parse('2026-07-16 10:05', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:10', 'Europe/Amsterdam'),
    ]);

    /** @var Event $coveringEvent */
    $coveringEvent = Event::factory()->create([
        'user_id' => $user->id,
        'event_type_id' => $eventTypeHeavy->id,
        'started_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:15', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect(Activity::count())->toBe(1)
        ->and(Activity::whereColumn('started_at', '>=', 'ended_at')->count())->toBe(0)
        ->and($coveredEvent->fresh()->activity_id)->toBeNull();
});

test('an event fully covered by a matching activity attaches to that activity', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();

    $sameState = [
        'event_type_id' => EventType::factory()->state(['weight' => 1])->create()->id,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'user_id' => $user->id,
    ];

    /** @var Event $coveringEvent */
    $coveringEvent = Event::factory()->create(array_merge($sameState, [
        'started_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:30', 'Europe/Amsterdam'),
    ]));

    /** @var Event $coveredEvent */
    $coveredEvent = Event::factory()->create(array_merge($sameState, [
        'started_at' => Carbon::parse('2026-07-16 10:05', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:10', 'Europe/Amsterdam'),
    ]));

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect(Activity::count())->toBe(1)
        ->and($coveredEvent->fresh()->activity_id)->toBe($coveringEvent->fresh()->activity_id);
});

test('a covered event of another customer stays unattached instead of mixing customers', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);

    $eventTypeId = EventType::factory()->state(['weight' => 1])->create()->id;
    $user = User::factory()->create();

    /** @var Event $coveringEvent */
    $coveringEvent = Event::factory()->create([
        'event_type_id' => $eventTypeId,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'user_id' => $user->id,
        'started_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:30', 'Europe/Amsterdam'),
    ]);

    /** @var Event $coveredEvent */
    $coveredEvent = Event::factory()->create([
        'event_type_id' => $eventTypeId,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'user_id' => $user->id,
        'started_at' => Carbon::parse('2026-07-16 10:05', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:10', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect(Activity::count())->toBe(1)
        ->and($coveredEvent->fresh()->activity_id)->toBeNull();
});

test('loads the event type of an activity', function () {
    Illuminate\Support\Facades\Event::fake();
    $eventType = EventType::firstOrCreate(['id' => 'ticket_saved'], ['weight' => 1]);

    $activity = Activity::factory()->create(['event_type_id' => $eventType->id]);

    expect($activity->eventType)->toBeInstanceOf(EventType::class)
        ->and($activity->eventType->id)->toBe('ticket_saved');
});
