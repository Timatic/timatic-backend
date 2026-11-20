<?php

use App\Models\Activity;
use App\Models\Entry;
use App\Models\EntrySuggestion;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes events that are older than the retention period', function () {
    $recent = Event::factory()->create(['created_at' => now()->subDays(29)]);
    $expired = Event::factory()->create(['created_at' => now()->subDays(31)]);

    $this->artisan('model:prune', ['--model' => [Event::class]])->assertSuccessful();

    expect(Event::whereKey($recent->id)->exists())->toBeTrue()
        ->and(Event::whereKey($expired->id)->exists())->toBeFalse();
});

it('deletes activities that are older than the retention period', function () {
    $recent = Activity::factory()->create(['created_at' => now()->subDays(29)]);
    $expired = Activity::factory()->create(['created_at' => now()->subDays(31)]);

    $this->artisan('model:prune', ['--model' => [Activity::class]])->assertSuccessful();

    expect(Activity::whereKey($recent->id)->exists())->toBeTrue()
        ->and(Activity::whereKey($expired->id)->exists())->toBeFalse();
});

it('deletes open suggestions that are older than the retention period', function () {
    $recent = EntrySuggestion::factory()->create(['created_at' => now()->subDays(29)]);
    $expired = EntrySuggestion::factory()->create(['created_at' => now()->subDays(31)]);
    $dismissed = EntrySuggestion::factory()->create(['created_at' => now()->subDays(31), 'deleted_at' => now()->subDays(31)]);
    $booked = EntrySuggestion::factory()->create(['created_at' => now()->subDays(31)]);
    Entry::factory()->create(['entry_suggestion_id' => $booked->id, 'deleted_at' => now()]);

    $this->artisan('model:prune', ['--model' => [EntrySuggestion::class]])->assertSuccessful();

    expect(EntrySuggestion::withTrashed()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$recent->id, $dismissed->id, $booked->id])->sort()->values()->all())
        ->and(EntrySuggestion::withTrashed()->whereKey($expired->id)->exists())->toBeFalse();
});
