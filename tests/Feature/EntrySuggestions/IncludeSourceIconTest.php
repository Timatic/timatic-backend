<?php

use App\Models\Activity;
use App\Models\Budget;
use App\Models\EntrySuggestion;
use App\Models\Event;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('includes the icon of the source an activity was built from', function () {
    /** @var User $user */
    $user = $this->loginUser(permissions: ['entry-suggestions.read']);

    $source = Source::factory()->create(['id' => 'a_source']);

    $entrySuggestion = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'budget_id' => Budget::factory(),
    ]);

    $activity = Activity::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $entrySuggestion->id,
        'source_id' => $source->id,
    ]);

    $activity->events()->attach(Event::factory()->create(['user_id' => $user->id, 'source_id' => $source->id]));

    $response = $this->getJson('entry-suggestions?include=activities.events.source')->assertSuccessful();

    $includedSource = collect($response->json('included'))->firstWhere('type', 'sources');

    expect($includedSource['attributes']['iconUrl'])->toEqual(asset('icons/a_source.svg'));
});
