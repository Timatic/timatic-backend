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
    $date = Carbon::now('Europe/Amsterdam')->subMonth()->startOfMonth()->addDays(15);
    $user = User::factory()->create();
    $stale = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'date' => $date->toDateString(),
        'ticket_number' => 'STALE-1',
    ]);
    Event::factory()->create([
        'user_id' => $user->id,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => EventType::factory()->create(['weight' => 1])->id,
        'started_at' => $date->copy()->setTime(9, 0),
        'ended_at' => $date->copy()->setTime(9, 30),
    ]);

    $this->artisan('timatic:rebundle-suggestions')->assertSuccessful();

    expect(EntrySuggestion::withTrashed()->whereKey($stale->id)->exists())->toBeFalse()
        ->and(EntrySuggestion::sole()->ticket_number)->toBe('TIC-1');
});

test('rebundling respects the user filter', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $date = Carbon::now('Europe/Amsterdam')->subMonth()->startOfMonth()->addDays(15);
    $targetUser = User::factory()->create();
    $otherUser = User::factory()->create();
    $stale = EntrySuggestion::factory()->create([
        'user_id' => $targetUser->id,
        'date' => $date->toDateString(),
        'ticket_number' => 'STALE-1',
    ]);
    $untouched = EntrySuggestion::factory()->create([
        'user_id' => $otherUser->id,
        'date' => $date->toDateString(),
    ]);
    Event::factory()->create([
        'user_id' => $targetUser->id,
        'ticket_number' => 'TIC-1',
        'event_type_id' => EventType::factory()->create(['weight' => 1])->id,
        'started_at' => $date->copy()->setTime(9, 0),
        'ended_at' => $date->copy()->setTime(9, 30),
    ]);

    $this->artisan('timatic:rebundle-suggestions', ['--user' => $targetUser->id])->assertSuccessful();

    expect(EntrySuggestion::query()->whereKey($untouched->id)->exists())->toBeTrue()
        ->and(EntrySuggestion::withTrashed()->whereKey($stale->id)->exists())->toBeFalse()
        ->and(EntrySuggestion::where('user_id', $targetUser->id)->sole()->ticket_number)->toBe('TIC-1');
});
