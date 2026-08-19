<?php

use App\Events\EventCreated;
use App\Models\Entry;
use App\Models\EntrySuggestion;
use App\Models\Event;
use App\Models\User;
use App\Queries\UserDayEvents;
use App\Queries\UserDismissedSuggestionsOnDate;
use App\Queries\UserEntriesInDay;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user day events returns only events ending within the day with their event type loaded', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $inside = Event::factory()->create([
        'user_id' => $user->id,
        'ended_at' => Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam'),
    ]);
    Event::factory()->create([
        'user_id' => $user->id,
        'ended_at' => Carbon::parse('2026-07-17 00:30', 'Europe/Amsterdam'),
    ]);
    Event::factory()->create([
        'ended_at' => Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam'),
    ]);

    $events = UserDayEvents::query($user->id, Carbon::parse('2026-07-16', 'Europe/Amsterdam'))->get();

    expect($events->pluck('id')->all())->toBe([$inside->id])
        ->and($events->first()->relationLoaded('eventType'))->toBeTrue();
});

test('user entries in day returns entries overlapping the day bounds', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $overlapping = Entry::factory()->create([
        'user_id' => $user->id,
        'started_at' => Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
    ]);
    Entry::factory()->create([
        'user_id' => $user->id,
        'started_at' => Carbon::parse('2026-07-15 09:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-15 10:00', 'Europe/Amsterdam'),
    ]);

    $entries = UserEntriesInDay::query($user->id, Carbon::parse('2026-07-16', 'Europe/Amsterdam'))->get();

    expect($entries->pluck('id')->all())->toBe([$overlapping->id]);
});

test('user dismissed suggestions returns trashed suggestions without entry for the date', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $dismissed = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
        'deleted_at' => now(),
    ]);
    $accepted = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
        'deleted_at' => now(),
    ]);
    Entry::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $accepted->id,
        'started_at' => Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam'),
    ]);
    EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
    ]);

    $suggestions = UserDismissedSuggestionsOnDate::query($user->id, Carbon::parse('2026-07-16', 'Europe/Amsterdam'))->get();

    expect($suggestions->pluck('id')->all())->toBe([$dismissed->id]);
});
