<?php

use App\Events\EventCreated;
use App\Models\EntrySuggestion;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('rebundling rebuilds the user-days of open suggestions from their events', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $user = User::factory()->create();
    $stale = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
        'ticket_number' => 'STALE-1',
    ]);
    Event::factory()->create([
        'user_id' => $user->id,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => EventType::factory()->create(['weight' => 1])->id,
        'started_at' => Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam'),
    ]);

    $this->artisan('timatic:rebundle-suggestions')->assertSuccessful();

    expect(EntrySuggestion::withTrashed()->whereKey($stale->id)->exists())->toBeFalse()
        ->and(EntrySuggestion::sole()->ticket_number)->toBe('TIC-1');
});

test('rebundling respects the user filter', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $targetUser = User::factory()->create();
    $otherUser = User::factory()->create();
    EntrySuggestion::factory()->create([
        'user_id' => $targetUser->id,
        'date' => '2026-07-16',
    ]);
    $untouched = EntrySuggestion::factory()->create([
        'user_id' => $otherUser->id,
        'date' => '2026-07-16',
    ]);

    $this->artisan('timatic:rebundle-suggestions', ['--user' => $targetUser->id])->assertSuccessful();

    expect(EntrySuggestion::query()->whereKey($untouched->id)->exists())->toBeTrue()
        ->and(EntrySuggestion::query()->where('user_id', $targetUser->id)->count())->toBe(0);
});
