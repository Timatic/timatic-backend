<?php

use App\Events\EventCreated;
use App\Jobs\RebuildUserDay;
use App\Models\Activity;
use App\Models\Budget;
use App\Models\Entry;
use App\Models\EntrySuggestion;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Source;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
    $acceptedSuggestion = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
    ]);
    Entry::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $acceptedSuggestion->id,
        'started_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 11:00', 'Europe/Amsterdam'),
    ]);
    Event::factory()->create([
        'user_id' => $user->id,
        'customer_id' => 'customerX',
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

test('a suggestion whose entry was deleted survives the rebuild', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $acceptedSuggestion = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
    ]);
    $deletedEntry = Entry::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $acceptedSuggestion->id,
        'started_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 11:00', 'Europe/Amsterdam'),
        'deleted_at' => now(),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect(EntrySuggestion::query()->whereKey($acceptedSuggestion->id)->exists())->toBeTrue()
        ->and(Entry::withTrashed()->find($deletedEntry->id)->entry_suggestion_id)->toBe($acceptedSuggestion->id);
});

test('the unique id combines user and date', function () {
    expect((new RebuildUserDay(1, '2026-07-16'))->uniqueId())->toBe('1:2026-07-16');
});

test('a customerless event takes customer and budget from the entry booked on its sequence after the events are pruned', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $budget = Budget::factory()->create();
    $eventTypeId = EventType::factory()->create(['weight' => 1])->id;
    $usedSuggestion = EntrySuggestion::factory()->create(['user_id' => $user->id, 'date' => '2026-07-09']);
    DB::table('entry_suggestion_sequences')->insert([
        'entry_suggestion_id' => $usedSuggestion->id,
        'source_id' => $source->id,
        'external_id' => 'weekly-sync',
    ]);
    $usedSuggestion->delete();
    Entry::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $usedSuggestion->id,
        'customer_id' => $budget->customer_id,
        'budget_id' => $budget->id,
        'is_internal' => false,
        'started_at' => Carbon::parse('2026-07-09 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-09 10:30', 'Europe/Amsterdam'),
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
    $usedSuggestion = EntrySuggestion::factory()->create(['user_id' => $user->id, 'date' => '2026-07-09']);
    DB::table('entry_suggestion_sequences')->insert([
        'entry_suggestion_id' => $usedSuggestion->id,
        'source_id' => $source->id,
        'external_id' => 'weekly-sync',
    ]);
    $usedSuggestion->delete();
    Entry::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $usedSuggestion->id,
        'customer_id' => $budget->customer_id,
        'budget_id' => $budget->id,
        'is_internal' => false,
        'started_at' => Carbon::parse('2026-07-09 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-09 10:30', 'Europe/Amsterdam'),
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

test('rebuilding stores the event sequences of each suggestion', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $eventTypeId = EventType::factory()->create(['weight' => 1])->id;
    Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'external_id' => 'weekly-sync',
        'customer_id' => 'customerX',
        'ticket_number' => null,
        'event_type_id' => $eventTypeId,
        'started_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:30', 'Europe/Amsterdam'),
    ]);
    Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'external_id' => null,
        'customer_id' => 'customerX',
        'ticket_number' => null,
        'event_type_id' => $eventTypeId,
        'started_at' => Carbon::parse('2026-07-16 10:30', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:45', 'Europe/Amsterdam'),
    ]);

    RebuildUserDay::dispatchSync($user->id, '2026-07-16');

    expect(DB::table('entry_suggestion_sequences')->get(['entry_suggestion_id', 'source_id', 'external_id'])->map(fn (object $row) => (array) $row)->all())
        ->toBe([['entry_suggestion_id' => EntrySuggestion::sole()->id, 'source_id' => $source->id, 'external_id' => 'weekly-sync']]);
});
