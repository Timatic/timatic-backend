<?php

use App\Events\EventCreated;
use App\Jobs\RebuildUserDay;
use App\Models\Activity;
use App\Models\Budget;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\EntrySuggestion;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Source;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('rebuilding a day projects activities and suggestions from its events', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'user_id' => $user->id,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => EventType::factory()->create(['weight' => 1])->id,
        'started_at' => Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    $activity = Activity::sole();
    $suggestion = EntrySuggestion::sole();
    expect($activity->started_at->toDateTimeString())->toBe('2026-07-16 09:00:00')
        ->and($activity->ended_at->toDateTimeString())->toBe('2026-07-16 09:30:00')
        ->and($activity->entry_suggestion_id)->toBe($suggestion->id)
        ->and($suggestion->date)->toBe('2026-07-16')
        ->and($event->fresh()->activities->pluck('id')->all())->toBe([$activity->id]);
});

test('rebuilding replaces the previous activities and force-deletes open suggestions of the day', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $staleSuggestion = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
    ]);
    $staleActivity = Activity::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $staleSuggestion->id,
        'started_at' => Carbon::parse('2026-07-16 08:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 08:30', 'Europe/Amsterdam'),
    ]);
    Event::factory()->create([
        'user_id' => $user->id,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => EventType::factory()->create(['weight' => 1])->id,
        'started_at' => Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect(Activity::query()->whereKey($staleActivity->id)->exists())->toBeFalse()
        ->and(EntrySuggestion::withTrashed()->whereKey($staleSuggestion->id)->exists())->toBeFalse()
        ->and(EntrySuggestion::count())->toBe(1)
        ->and(Activity::count())->toBe(1);
});

test('a dismissed suggestion suppresses its group but the activity is still saved', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $dismissed = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
        'customer_id' => 'customerX',
        'budget_id' => null,
        'ticket_number' => 'TIC-1',
        'is_internal' => null,
        'deleted_at' => now(),
    ]);
    Event::factory()->create([
        'user_id' => $user->id,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => EventType::factory()->create(['weight' => 1])->id,
        'started_at' => Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect(EntrySuggestion::count())->toBe(0)
        ->and(EntrySuggestion::withTrashed()->whereKey($dismissed->id)->exists())->toBeTrue()
        ->and(Activity::sole()->entry_suggestion_id)->toBeNull();
});

test('an entry blocks its period and entry-backed suggestions survive the rebuild', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $customer = Customer::factory()->create();
    $acceptedSuggestion = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
    ]);
    Entry::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $acceptedSuggestion->id,
        'customer_id' => $customer->id,
        'started_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 11:00', 'Europe/Amsterdam'),
    ]);
    Event::factory()->create([
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'ticket_number' => 'TIC-1',
        'event_type_id' => EventType::factory()->create(['weight' => 1])->id,
        'started_at' => Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:30', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    $activity = Activity::sole();
    expect($activity->started_at->toDateTimeString())->toBe('2026-07-16 09:30:00')
        ->and($activity->ended_at->toDateTimeString())->toBe('2026-07-16 10:00:00')
        ->and(EntrySuggestion::query()->whereKey($acceptedSuggestion->id)->exists())->toBeTrue();
});

test('a booked entry only shortens activities of its own customer on the rebuild', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    [$bookedCustomer, $otherCustomer] = Customer::factory()->count(2)->create();
    Entry::factory()->create([
        'user_id' => $user->id,
        'customer_id' => $bookedCustomer->id,
        'ticket_number' => 'TIC-1',
        'started_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 11:00', 'Europe/Amsterdam'),
    ]);
    $eventTypeId = EventType::factory()->create(['weight' => 1])->id;
    Event::factory()->create([
        'user_id' => $user->id,
        'customer_id' => $bookedCustomer->id,
        'ticket_number' => 'TIC-2',
        'event_type_id' => $eventTypeId,
        'started_at' => Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:30', 'Europe/Amsterdam'),
    ]);
    Event::factory()->create([
        'user_id' => $user->id,
        'customer_id' => $otherCustomer->id,
        'ticket_number' => 'TIC-3',
        'event_type_id' => $eventTypeId,
        'started_at' => Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:30', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    $periods = Activity::query()->orderBy('ticket_number')->get()
        ->map(fn (Activity $activity) => $activity->ticket_number.' '.$activity->started_at->format('H:i').'-'.$activity->ended_at->format('H:i'))
        ->all();
    expect($periods)->toBe(['TIC-2 09:30-10:00', 'TIC-3 09:30-10:30']);
});

test('the unique id combines user and date', function () {
    expect((new RebuildUserDay(1, '2026-07-16'))->uniqueId())->toBe('1:2026-07-16');
});

test('a customerless event takes customer and budget from the entry booked on the previous event of its sequence', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $budget = Budget::factory()->create();
    $eventTypeId = EventType::factory()->create(['weight' => 1])->id;
    $previousMeeting = Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'external_id' => 'weekly-sync',
        'customer_id' => null,
        'ticket_id' => null,
        'ticket_number' => null,
        'event_type_id' => $eventTypeId,
        'started_at' => Carbon::parse('2026-07-09 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-09 10:30', 'Europe/Amsterdam'),
    ]);
    $usedSuggestion = EntrySuggestion::factory()->create(['user_id' => $user->id, 'date' => '2026-07-09']);
    Activity::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $usedSuggestion->id,
        'started_at' => $previousMeeting->started_at,
        'ended_at' => $previousMeeting->ended_at,
    ])->events()->attach($previousMeeting);
    $usedSuggestion->delete();
    Entry::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $usedSuggestion->id,
        'customer_id' => $budget->customer_id,
        'budget_id' => $budget->id,
        'is_internal' => false,
        'started_at' => $previousMeeting->started_at,
        'ended_at' => $previousMeeting->ended_at,
    ]);
    Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'external_id' => 'weekly-sync',
        'customer_id' => null,
        'ticket_id' => null,
        'ticket_number' => null,
        'event_type_id' => $eventTypeId,
        'started_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:30', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    $suggestion = EntrySuggestion::query()->where('date', '2026-07-16')->sole();
    expect((int) $suggestion->customer_id)->toBe($budget->customer_id)
        ->and($suggestion->budget_id)->toBe($budget->id)
        ->and($suggestion->is_internal)->toBeFalse();
});

test('a dismissed suggestion for the customer learned from the event sequence stays dismissed', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $budget = Budget::factory()->create();
    $eventTypeId = EventType::factory()->create(['weight' => 1])->id;
    $previousMeeting = Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'external_id' => 'weekly-sync',
        'customer_id' => null,
        'ticket_id' => null,
        'ticket_number' => null,
        'event_type_id' => $eventTypeId,
        'started_at' => Carbon::parse('2026-07-09 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-09 10:30', 'Europe/Amsterdam'),
    ]);
    $usedSuggestion = EntrySuggestion::factory()->create(['user_id' => $user->id, 'date' => '2026-07-09']);
    Activity::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $usedSuggestion->id,
        'started_at' => $previousMeeting->started_at,
        'ended_at' => $previousMeeting->ended_at,
    ])->events()->attach($previousMeeting);
    $usedSuggestion->delete();
    Entry::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $usedSuggestion->id,
        'customer_id' => $budget->customer_id,
        'budget_id' => $budget->id,
        'is_internal' => false,
        'started_at' => $previousMeeting->started_at,
        'ended_at' => $previousMeeting->ended_at,
    ]);
    Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'external_id' => 'weekly-sync',
        'customer_id' => null,
        'ticket_id' => null,
        'ticket_number' => null,
        'event_type_id' => $eventTypeId,
        'started_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:30', 'Europe/Amsterdam'),
    ]);
    EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
        'customer_id' => $budget->customer_id,
        'budget_id' => $budget->id,
        'ticket_number' => null,
        'is_internal' => false,
        'deleted_at' => now(),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect(EntrySuggestion::query()->where('date', '2026-07-16')->exists())->toBeFalse()
        ->and((int) Activity::query()->where('started_at', '>=', '2026-07-16')->sole()->customer_id)->toBe($budget->customer_id);
});
