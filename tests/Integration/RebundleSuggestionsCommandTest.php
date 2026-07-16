<?php

use App\Models\Activity;
use App\Models\Entry;
use App\Models\EntrySuggestion;
use App\Models\Source;
use App\Models\User;
use App\Services\SuggestionBundler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;

uses(RefreshDatabase::class);

it('rebundles one-to-one suggestions into stacks', function () {
    EventFacade::fake();
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $bundler = app(SuggestionBundler::class);

    foreach (['09:00:00', '11:00:00', '13:00:00'] as $time) {
        $activity = Activity::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'customer_id' => '1',
            'ticket_number' => 'PIO-12',
            'started_at' => '2026-06-04 '.$time,
            'ended_at' => '2026-06-04 '.$time,
        ]);
        $bundler->createNewSuggestionFor($activity);
    }

    expect(EntrySuggestion::count())->toBe(3);

    $this->artisan('timatic:rebundle-suggestions')->assertSuccessful();

    expect(EntrySuggestion::count())->toBe(1)
        ->and(EntrySuggestion::first()->activities()->count())->toBe(3);
});

it('leaves accepted suggestions and their activities untouched', function () {
    EventFacade::fake();
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $bundler = app(SuggestionBundler::class);

    $acceptedActivity = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 10:00:00',
    ]);
    $accepted = $bundler->createNewSuggestionFor($acceptedActivity);
    Entry::factory()->create(['entry_suggestion_id' => $accepted->id]);

    $openActivity = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 11:00:00',
        'ended_at' => '2026-06-04 12:00:00',
    ]);
    $bundler->createNewSuggestionFor($openActivity);

    $this->artisan('timatic:rebundle-suggestions')->assertSuccessful();

    $acceptedActivity->refresh();
    expect($acceptedActivity->entry_suggestion_id)->toBe($accepted->id)
        ->and($accepted->fresh()->activities()->count())->toBe(1);
});

it('leaves rejected suggestions and their activities untouched', function () {
    EventFacade::fake();
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $bundler = app(SuggestionBundler::class);

    $rejectedActivity = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 10:00:00',
    ]);
    $rejected = $bundler->createNewSuggestionFor($rejectedActivity);
    $rejected->delete();

    $this->artisan('timatic:rebundle-suggestions')->assertSuccessful();

    $rejectedActivity->refresh();
    expect($rejectedActivity->entry_suggestion_id)->toBe($rejected->id)
        ->and(EntrySuggestion::withTrashed()->count())->toBe(1);
});

it('scopes rebundling with the user option', function () {
    EventFacade::fake();
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $source = Source::factory()->create();
    $bundler = app(SuggestionBundler::class);

    foreach ([$userA, $userA, $userB] as $index => $user) {
        $activity = Activity::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'customer_id' => '1',
            'ticket_number' => 'PIO-12',
            'started_at' => '2026-06-04 '.(9 + $index).':00:00',
            'ended_at' => '2026-06-04 '.(9 + $index).':30:00',
        ]);
        $bundler->createNewSuggestionFor($activity);
    }

    $this->artisan('timatic:rebundle-suggestions', ['--user' => $userA->id])->assertSuccessful();

    expect(EntrySuggestion::where('user_id', $userA->id)->count())->toBe(1)
        ->and(EntrySuggestion::where('user_id', $userB->id)->count())->toBe(1);
});
