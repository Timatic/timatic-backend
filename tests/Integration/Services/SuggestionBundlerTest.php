<?php

use App\Models\Activity;
use App\Models\Budget;
use App\Models\Entry;
use App\Models\EntrySuggestion;
use App\Models\Source;
use App\Models\User;
use App\Services\SuggestionBundler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;

uses(RefreshDatabase::class);

it('stacks same-ticket activities of one day into one suggestion', function () {
    EventFacade::fake();
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $first = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 10:00:00',
    ]);
    $second = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 13:00:00',
        'ended_at' => '2026-06-04 14:00:00',
    ]);

    $bundler = app(SuggestionBundler::class);
    $firstSuggestion = $bundler->bundle($first);
    $secondSuggestion = $bundler->bundle($second);

    expect($secondSuggestion->id)->toBe($firstSuggestion->id)
        ->and(EntrySuggestion::count())->toBe(1)
        ->and($firstSuggestion->activities()->count())->toBe(2);
});

it('does not bundle a no-ticket activity into a ticketed suggestion', function () {
    EventFacade::fake();
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $ticketed = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 10:00:00',
    ]);
    $unticketed = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => null,
        'started_at' => '2026-06-04 10:30:00',
        'ended_at' => '2026-06-04 11:00:00',
    ]);

    $bundler = app(SuggestionBundler::class);
    $ticketedSuggestion = $bundler->bundle($ticketed);
    $unticketedSuggestion = $bundler->bundle($unticketed);

    expect($unticketedSuggestion->id)->not->toBe($ticketedSuggestion->id)
        ->and(EntrySuggestion::count())->toBe(2);
});

it('bundles no-ticket activities of the same customer and day together', function () {
    EventFacade::fake();
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $first = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => null,
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 10:00:00',
    ]);
    $second = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => null,
        'started_at' => '2026-06-04 13:00:00',
        'ended_at' => '2026-06-04 14:00:00',
    ]);

    $bundler = app(SuggestionBundler::class);
    $firstSuggestion = $bundler->bundle($first);
    $secondSuggestion = $bundler->bundle($second);

    expect($secondSuggestion->id)->toBe($firstSuggestion->id);
});

it('does not bundle activities of different budgets', function () {
    EventFacade::fake();
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $first = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'budget_id' => null,
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 10:00:00',
    ]);
    $second = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'budget_id' => Budget::factory()->create()->id,
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 13:00:00',
        'ended_at' => '2026-06-04 14:00:00',
    ]);

    $bundler = app(SuggestionBundler::class);
    $firstSuggestion = $bundler->bundle($first);
    $secondSuggestion = $bundler->bundle($second);

    expect($secondSuggestion->id)->not->toBe($firstSuggestion->id);
});

it('does not bundle activities of different days', function () {
    EventFacade::fake();
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $first = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 10:00:00',
    ]);
    $second = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-05 09:00:00',
        'ended_at' => '2026-06-05 10:00:00',
    ]);

    $bundler = app(SuggestionBundler::class);
    $firstSuggestion = $bundler->bundle($first);
    $secondSuggestion = $bundler->bundle($second);

    expect($secondSuggestion->id)->not->toBe($firstSuggestion->id);
});

it('does not reuse a rejected suggestion', function () {
    EventFacade::fake();
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $first = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 10:00:00',
    ]);

    $bundler = app(SuggestionBundler::class);
    $rejected = $bundler->bundle($first);
    $rejected->delete();

    $second = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 13:00:00',
        'ended_at' => '2026-06-04 14:00:00',
    ]);
    $suggestion = $bundler->bundle($second);

    expect($suggestion->id)->not->toBe($rejected->id);
});

it('does not reuse an accepted suggestion', function () {
    EventFacade::fake();
    $user = User::factory()->create();
    $source = Source::factory()->create();
    $first = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 10:00:00',
    ]);

    $bundler = app(SuggestionBundler::class);
    $accepted = $bundler->bundle($first);
    Entry::factory()->create(['entry_suggestion_id' => $accepted->id]);

    $second = Activity::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'started_at' => '2026-06-04 13:00:00',
        'ended_at' => '2026-06-04 14:00:00',
    ]);
    $suggestion = $bundler->bundle($second);

    expect($suggestion->id)->not->toBe($accepted->id);
});
