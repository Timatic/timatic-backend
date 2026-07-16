# Phase 1: Recomputable Suggestion Bundling — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Spec:** `docs/superpowers/specs/2026-07-16-suggestion-bundling-design.md`

**Goal:** Extract activity→suggestion bundling into a re-runnable `SuggestionBundler` service with strict ticket matching, add a rebundle command to prove it on real data, and stop rebase re-pushes from creating duplicate Bitbucket events.

**Architecture:** One service class holds all matching logic; the queued `CreateSuggestion` listener and a new artisan command both delegate to it, so a replay proves the exact production path. Rebase detection lives in the Bitbucket integration's `ProcessWebhookJob`, keyed on commit title + commit date (both survive a rebase; the SHA does not).

**Tech Stack:** Laravel 12, PHP 8.4, Pest 3, PHPStan level 8 (Larastan), Pint.

## Global Constraints

- Run `vendor/bin/pint --dirty --format agent` after modifying any PHP file.
- Before each commit: `composer prepare-commit` must pass (pint + phpstan level 8 + pest). For quicker loops: `vendor/bin/phpstan --error-format=json --no-progress` and `php artisan test --compact --filter=<name>`.
- Commit messages: Conventional Commits, imperative, lowercase, no AI attribution trailers.
- Code style: public methods first, private helpers after; no inline comments except constraints the code cannot show; explicit parameter and return types everywhere.
- Tests: Pest, atomic (no shared helper methods between tests except payload builders already conventional in this suite), `RefreshDatabase`, `Illuminate\Support\Facades\Event::fake()` wherever creating an `Activity`/`Event` model must not trigger the queued listeners.
- Feature flag: `timatic.feature.build_stacked_suggestions` (env `BUILD_STACKED_SUGGESTIONS`, default `false`). Tests that need stacking set `config()->set('timatic.feature.build_stacked_suggestions', true)`.
- Timezone: suggestion `date` is derived in `config('timatic.preferred_timezone')` (Europe/Amsterdam). Use midday UTC timestamps in tests to avoid date-boundary flakiness.
- **Task 4 touches `integrations/bitbucket` — the implementer MUST activate the `/timatic-integration` skill before writing any code there.**

---

### Task 1: `SuggestionBundler` service

**Files:**
- Create: `app/Services/SuggestionBundler.php`
- Test: `tests/Integration/Services/SuggestionBundlerTest.php`

**Interfaces:**
- Consumes: `App\Models\Activity`, `App\Models\EntrySuggestion` (existing).
- Produces: `SuggestionBundler::bundle(Activity $activity): EntrySuggestion` (find-or-create with strict matching) and `SuggestionBundler::createNewSuggestionFor(Activity $activity): EntrySuggestion` (always creates; used by the listener when the feature flag is off). Tasks 2 and 3 call exactly these two methods.

Matching rules (all must be equal; `null` only matches `null`): `user_id`, `customer_id`, `budget_id`, `is_internal`, `ticket_number`, and calendar date of `started_at` in the preferred timezone. Suggestions that are soft-deleted (rejected — excluded automatically by the `SoftDeletes` global scope) or that have an `Entry` (accepted) are never reused.

- [ ] **Step 1: Write the failing tests**

Create `tests/Integration/Services/SuggestionBundlerTest.php`:

```php
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
        'budget_id' => \App\Models\Budget::factory()->create()->id,
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Integration/Services/SuggestionBundlerTest.php`
Expected: FAIL — `Class "App\Services\SuggestionBundler" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Services/SuggestionBundler.php`:

```php
<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\EntrySuggestion;
use Illuminate\Database\Eloquent\Builder;

class SuggestionBundler
{
    public function bundle(Activity $activity): EntrySuggestion
    {
        $suggestion = $this->findMatchingSuggestion($activity)
            ?? $this->newSuggestionFromActivity($activity);

        return $this->attach($suggestion, $activity);
    }

    public function createNewSuggestionFor(Activity $activity): EntrySuggestion
    {
        return $this->attach($this->newSuggestionFromActivity($activity), $activity);
    }

    private function attach(EntrySuggestion $suggestion, Activity $activity): EntrySuggestion
    {
        $suggestion->save();
        $suggestion->activities()->save($activity);

        return $suggestion;
    }

    private function findMatchingSuggestion(Activity $activity): ?EntrySuggestion
    {
        $query = EntrySuggestion::query()
            ->whereDoesntHave('entry')
            ->where('user_id', $activity->user_id)
            ->where('date', $this->suggestionDateFor($activity));

        $this->whereNullable($query, 'customer_id', $activity->customer_id);
        $this->whereNullable($query, 'budget_id', $activity->budget_id);
        $this->whereNullable($query, 'ticket_number', $activity->ticket_number);
        $this->whereNullable($query, 'is_internal', $activity->is_internal);

        /** @var ?EntrySuggestion */
        return $query->first();
    }

    private function newSuggestionFromActivity(Activity $activity): EntrySuggestion
    {
        $suggestion = new EntrySuggestion;
        $suggestion->user_id = $activity->user_id;
        $suggestion->budget_id = $activity->budget_id;
        $suggestion->ticket_id = $activity->ticket_id;
        $suggestion->ticket_number = $activity->ticket_number;
        $suggestion->ticket_type = $activity->ticket_type;
        $suggestion->customer_id = $activity->customer_id;
        $suggestion->is_internal = $activity->is_internal;
        $suggestion->date = $this->suggestionDateFor($activity);

        return $suggestion;
    }

    private function suggestionDateFor(Activity $activity): string
    {
        return $activity->started_at
            ->setTimezone(config('timatic.preferred_timezone'))
            ->toDateString();
    }

    /**
     * @param  Builder<EntrySuggestion>  $query
     */
    private function whereNullable(Builder $query, string $column, mixed $value): void
    {
        if ($value === null) {
            $query->whereNull($column);
        } else {
            $query->where($column, $value);
        }
    }
}
```

The `whereNullable` helper exists because `->where($column, null)` compiles to `= NULL` and never matches — null must be strict-match via `whereNull`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Integration/Services/SuggestionBundlerTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Lint, analyse, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan --error-format=json --no-progress
git add app/Services/SuggestionBundler.php tests/Integration/Services/SuggestionBundlerTest.php
git commit -m "feat: bundle same-ticket activities of a day into one suggestion"
```

---

### Task 2: Delegate `CreateSuggestion` listener to the service

**Files:**
- Modify: `app/Listeners/CreateSuggestion.php` (full rewrite, currently 93 lines)
- Modify: `tests/Integration/CreateSuggestionTest.php` (expectations encode the old "null joins any ticket" semantics and must change — this is an approved behaviour change, not test deletion)

**Interfaces:**
- Consumes: `SuggestionBundler::bundle()` and `SuggestionBundler::createNewSuggestionFor()` from Task 1.
- Produces: unchanged listener contract — queued handler for `App\Events\ActivityCreated`.

Behaviour change to be aware of: the old listener back-filled `ticket_number` onto a null-ticket suggestion (old lines 47–50). Under strict matching a null-ticket activity never joins a ticketed suggestion and vice versa, so that back-fill is dead logic and is removed.

- [ ] **Step 1: Rewrite the listener**

Replace the full contents of `app/Listeners/CreateSuggestion.php`:

```php
<?php

namespace App\Listeners;

use App\Events\ActivityCreated;
use App\Services\SuggestionBundler;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateSuggestion implements ShouldQueue
{
    public function __construct(private readonly SuggestionBundler $bundler) {}

    public function handle(ActivityCreated $activityCreated): void
    {
        $activity = $activityCreated->getActivity();

        if (config('timatic.feature.build_stacked_suggestions')) {
            $this->bundler->bundle($activity);
        } else {
            $this->bundler->createNewSuggestionFor($activity);
        }
    }
}
```

- [ ] **Step 2: Run the existing listener tests to see which expectations now fail**

Run: `php artisan test --compact tests/Integration/CreateSuggestionTest.php`
Expected: the three tests currently pass trivially (flag defaults to false → they return early / skip). They must be updated to force the flag on and assert strict semantics — next step.

- [ ] **Step 3: Rewrite `tests/Integration/CreateSuggestionTest.php` for strict semantics**

Replace the full file contents:

```php
<?php

use App\Events\ActivityCreated;
use App\Listeners\CreateSuggestion;
use App\Models\Activity;
use App\Models\EntrySuggestion;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class);

uses(WithFaker::class);

beforeEach(function () {
    config()->set('timatic.feature.build_stacked_suggestions', true);

    foreach (['ticket_saved', 'issue_changed_to_done', 'ticket_tagged', 'calendar_event_finished'] as $id) {
        EventType::firstOrCreate(['id' => $id], ['weight' => 1]);
    }
});

test('linear activity stream bundles strictly per ticket', function () {
    Illuminate\Support\Facades\Event::fake();

    /** @var User $user */
    $user = User::factory()->create();

    $userId = $user->id;
    $customerId = $this->faker->numberBetween();
    $ticketId1 = $this->faker->numberBetween();
    $ticketId2 = $this->faker->numberBetween();

    $data = [
        1 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'issue_changed_to_done',
            'ticket_number' => null,
        ],
        2 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'ticket_saved',
            'ticket_number' => $ticketId1,
        ],
        3 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'ticket_saved',
            'ticket_number' => $ticketId1,
        ],
        4 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'ticket_tagged',
            'ticket_number' => null,
        ],
        5 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'calendar_event_finished',
            'ticket_number' => $ticketId2,
        ],
        6 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'ticket_saved',
            'ticket_number' => $ticketId2,
        ],
    ];
    foreach ($data as $key => $d) {
        /** @var Activity[] $activities */
        $activities[$key] = Activity::factory()
            ->has(
                Event::factory()->state($d)
            )->create($d);
    }

    /** @var CreateSuggestion $listener */
    $listener = app(CreateSuggestion::class);

    foreach ($activities as $activity) {
        $listener->handle(new ActivityCreated($activity));
    }

    foreach ($activities as $activity) {
        $activity->refresh();
    }

    expect($activities[1]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($activities[4]->entry_suggestion_id)->toEqual($activities[1]->entry_suggestion_id);

    expect($activities[2]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($activities[3]->entry_suggestion_id)->toEqual($activities[2]->entry_suggestion_id);
    expect($activities[2]->entry_suggestion_id)->not->toEqual($activities[1]->entry_suggestion_id);

    expect($activities[5]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($activities[6]->entry_suggestion_id)->toEqual($activities[5]->entry_suggestion_id);
    expect($activities[5]->entry_suggestion_id)->not->toEqual($activities[2]->entry_suggestion_id);
});

test('rejected suggestion is not reused for later activities', function () {
    Illuminate\Support\Facades\Event::fake();

    /** @var User $user */
    $user = User::factory()->create();

    $userId = $user->id;
    $customerId = $this->faker->numberBetween();
    $ticketId = $this->faker->numberBetween();
    $state = [
        'user_id' => $userId,
        'customer_id' => $customerId,
        'event_type_id' => 'ticket_saved',
        'ticket_number' => $ticketId,
    ];

    /** @var Activity $first */
    $first = Activity::factory()->has(Event::factory()->state($state))->create($state);

    /** @var CreateSuggestion $listener */
    $listener = app(CreateSuggestion::class);
    $listener->handle(new ActivityCreated($first));

    $first->refresh();
    $first->entrySuggestion?->delete();

    /** @var Activity $second */
    $second = Activity::factory()->has(Event::factory()->state($state))->create($state);
    $listener->handle(new ActivityCreated($second));

    $second->refresh();
    expect($second->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($second->entry_suggestion_id)->not->toEqual($first->entry_suggestion_id);
});

test('flag off keeps one suggestion per activity', function () {
    Illuminate\Support\Facades\Event::fake();
    config()->set('timatic.feature.build_stacked_suggestions', false);

    /** @var User $user */
    $user = User::factory()->create();

    $state = [
        'user_id' => $user->id,
        'customer_id' => $this->faker->numberBetween(),
        'event_type_id' => 'ticket_saved',
        'ticket_number' => $this->faker->numberBetween(),
    ];

    /** @var Activity $first */
    $first = Activity::factory()->has(Event::factory()->state($state))->create($state);
    /** @var Activity $second */
    $second = Activity::factory()->has(Event::factory()->state($state))->create($state);

    /** @var CreateSuggestion $listener */
    $listener = app(CreateSuggestion::class);
    $listener->handle(new ActivityCreated($first));
    $listener->handle(new ActivityCreated($second));

    $first->refresh();
    $second->refresh();

    expect(EntrySuggestion::count())->toBe(2);
    expect($first->entry_suggestion_id)->not->toEqual($second->entry_suggestion_id);
});
```

Note: the old tests `activity stream with dangling activity` and `outlook activities without ticket id` asserted the removed "null-ticket suggestion absorbs a ticket" behaviour; their scenarios are now covered by the strict cases above and by the Task 1 service tests.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Integration/CreateSuggestionTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Run the full suggestion-related suites to catch regressions**

Run: `php artisan test --compact tests/Integration tests/Feature/EntrySuggestions`
Expected: PASS.

- [ ] **Step 6: Lint, analyse, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan --error-format=json --no-progress
git add app/Listeners/CreateSuggestion.php tests/Integration/CreateSuggestionTest.php
git commit -m "refactor: delegate suggestion creation to SuggestionBundler with strict ticket matching"
```

---

### Task 3: `timatic:rebundle-suggestions` command

**Files:**
- Create: `app/Console/Commands/RebundleSuggestionsCommand.php`
- Test: `tests/Integration/RebundleSuggestionsCommandTest.php`
- Modify: `README.md` (document the user-executed command)

**Interfaces:**
- Consumes: `SuggestionBundler::bundle()` from Task 1.
- Produces: artisan command `timatic:rebundle-suggestions {--user=} {--from=} {--to=}`.

Critical ordering constraint: `activities.entry_suggestion_id` has FK `ON DELETE CASCADE` to `entry_suggestions` — activities MUST be detached (set to null) **before** the suggestions are deleted, or the activities are destroyed with them.

- [ ] **Step 1: Write the failing tests**

Create `tests/Integration/RebundleSuggestionsCommandTest.php`:

```php
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
            'started_at' => '2026-06-04 0'.(9 + $index).':00:00',
            'ended_at' => '2026-06-04 0'.(9 + $index).':30:00',
        ]);
        $bundler->createNewSuggestionFor($activity);
    }

    $this->artisan('timatic:rebundle-suggestions', ['--user' => $userA->id])->assertSuccessful();

    expect(EntrySuggestion::where('user_id', $userA->id)->count())->toBe(1)
        ->and(EntrySuggestion::where('user_id', $userB->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Integration/RebundleSuggestionsCommandTest.php`
Expected: FAIL — command `timatic:rebundle-suggestions` does not exist.

- [ ] **Step 3: Write the command**

Create via `php artisan make:command RebundleSuggestionsCommand --no-interaction`, then replace contents:

```php
<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\EntrySuggestion;
use App\Services\SuggestionBundler;
use Illuminate\Console\Command;

class RebundleSuggestionsCommand extends Command
{
    protected $signature = 'timatic:rebundle-suggestions
        {--user= : Only rebundle suggestions of this user id}
        {--from= : Only rebundle suggestions on or after this date (Y-m-d)}
        {--to= : Only rebundle suggestions on or before this date (Y-m-d)}';

    protected $description = 'Delete open (not accepted, not rejected) suggestions and rebundle their activities chronologically';

    public function handle(SuggestionBundler $bundler): int
    {
        $suggestionIds = EntrySuggestion::query()
            ->whereDoesntHave('entry')
            ->when($this->option('user'), fn ($query, $user) => $query->where('user_id', $user))
            ->when($this->option('from'), fn ($query, $from) => $query->where('date', '>=', $from))
            ->when($this->option('to'), fn ($query, $to) => $query->where('date', '<=', $to))
            ->pluck('id');

        $activityIds = Activity::query()
            ->whereIn('entry_suggestion_id', $suggestionIds)
            ->pluck('id');

        // detach first: activities.entry_suggestion_id cascades on suggestion delete
        Activity::query()->whereIn('id', $activityIds)->update(['entry_suggestion_id' => null]);
        EntrySuggestion::query()->whereKey($suggestionIds)->forceDelete();

        Activity::query()
            ->whereIn('id', $activityIds)
            ->orderBy('started_at')
            ->get()
            ->each(fn (Activity $activity) => $bundler->bundle($activity));

        $this->info(sprintf(
            'Rebundled %d activities from %d suggestions into %d suggestions.',
            $activityIds->count(),
            $suggestionIds->count(),
            EntrySuggestion::query()->whereIn('id', Activity::query()->whereIn('id', $activityIds)->pluck('entry_suggestion_id'))->count(),
        ));

        return self::SUCCESS;
    }
}
```

Note: the command bundles unconditionally (ignores the `build_stacked_suggestions` flag) — that is its purpose: proving stacking before the flag is enabled.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Integration/RebundleSuggestionsCommandTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Document the command in README.md**

Add under the existing commands/usage section of `README.md`:

```markdown
### Rebundle entry suggestions

Deletes all open (not accepted, not rejected) entry suggestions and rebundles their
activities chronologically using the current matching rules:

​```bash
php artisan timatic:rebundle-suggestions [--user=1] [--from=2026-06-01] [--to=2026-06-30]
​```
```

(Remove the zero-width escapes around the inner code fence when pasting.)

- [ ] **Step 6: Lint, analyse, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan --error-format=json --no-progress
git add app/Console/Commands/RebundleSuggestionsCommand.php tests/Integration/RebundleSuggestionsCommandTest.php README.md
git commit -m "feat: add rebundle command to recompute open entry suggestions"
```

---

### Task 4: Bitbucket rebase detection

**⚠️ Activate the `/timatic-integration` skill before writing any code in this task — it touches `integrations/bitbucket`.**

**Files:**
- Modify: `integrations/bitbucket/src/Jobs/ProcessWebhookJob.php` (`handlePush()` at lines 47–56, add two private methods, generalize the existing exists-check)
- Create: `integrations/bitbucket/database/migrations/2026_07_16_000001_seed_rebase_event_type.php`
- Test: `tests/Integration/Bitbucket/ProcessPushWebhookJobTest.php` (new file)

**Interfaces:**
- Consumes: existing private helpers of `ProcessWebhookJob`: `createEvent()`, `createEventFromCommit()`, `splitCommitMessage()`, `extractEmail()`.
- Produces: new event type id `rebase` (weight 10); pushes containing already-ingested commits create one `rebase` event instead of duplicate `commit_pushed` events.

Detection key: user (by commit author email) + source `bitbucket` + event type + commit title + commit date. Title and commit date survive a rebase; the SHA does not, so the SHA is deliberately not used. The webhook's `change.forced` flag turned out redundant: content-based detection also catches known commits on non-forced pushes (e.g. the same commit pushed to a second branch), and a forced push containing only new commits has nothing to deduplicate — so `forced` stays unread (deviation from the spec's "corroboration" wording, in favour of one authoritative signal).

- [ ] **Step 1: Write the seed migration**

Create `integrations/bitbucket/database/migrations/2026_07_16_000001_seed_rebase_event_type.php` (mirrors `2026_04_14_000002_seed_bitbucket_pr_event_types.php`):

```php
<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $eventType = EventType::firstOrNew(['id' => 'rebase']);
        $eventType->weight = 10;
        $eventType->save();
    }

    public function down(): void
    {
        EventType::where('id', 'rebase')->delete();
    }
};
```

- [ ] **Step 2: Write the failing tests**

Create `tests/Integration/Bitbucket/ProcessPushWebhookJobTest.php`. Helper function names must not collide with `prPayload`/`createMappingWithCustomer` in `ProcessPrWebhookJobTest.php` — Pest test files share one global namespace.

```php
<?php

use App\Integrations\TicketService;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Timatic\Bitbucket\Jobs\ProcessWebhookJob;
use Timatic\Bitbucket\Models\RepositoryMapping;

uses(RefreshDatabase::class);

/**
 * @param  list<array{message: string, date: string}>  $commits
 * @return array<string, mixed>
 */
function pushPayload(string $email, array $commits): array
{
    return [
        'push' => [
            'changes' => [[
                'new' => ['name' => 'feature/test'],
                'commits' => array_map(fn (array $commit) => [
                    'hash' => fake()->sha1(),
                    'message' => $commit['message'],
                    'date' => $commit['date'],
                    'author' => ['raw' => 'Test User <'.$email.'>'],
                ], $commits),
            ]],
        ],
        'repository' => ['full_name' => 'workspace/repo'],
    ];
}

function pushMapping(): RepositoryMapping
{
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $integration = Integration::create(['name' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);

    return RepositoryMapping::create([
        'integration_id' => $integration->id,
        'workspace_slug' => 'workspace',
        'repository_slug' => 'repo',
        'repository_name' => 'repo',
        'customer_id' => $customer->id,
        'budget_id' => null,
    ]);
}

it('creates a commit_pushed event for a new commit', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $mapping = pushMapping();
    $payload = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
    ]);

    new ProcessWebhookJob($payload, $mapping, 'repo:push')->handle(app(TicketService::class));

    expect(Event::where('event_type_id', 'commit_pushed')->count())->toBe(1)
        ->and(Event::where('event_type_id', 'rebase')->count())->toBe(0);
});

it('creates one rebase event instead of duplicate commit events for known commits', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $mapping = pushMapping();
    $payload = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
        ['message' => 'fix pest', 'date' => '2026-06-05T09:40:00+00:00'],
    ]);

    new ProcessWebhookJob($payload, $mapping, 'repo:push')->handle(app(TicketService::class));
    new ProcessWebhookJob($payload, $mapping, 'repo:push')->handle(app(TicketService::class));

    expect(Event::where('event_type_id', 'commit_pushed')->count())->toBe(2)
        ->and(Event::where('event_type_id', 'rebase')->count())->toBe(1);
});

it('does not create a second rebase event when the same push is replayed again', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $mapping = pushMapping();
    $payload = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
    ]);

    new ProcessWebhookJob($payload, $mapping, 'repo:push')->handle(app(TicketService::class));
    new ProcessWebhookJob($payload, $mapping, 'repo:push')->handle(app(TicketService::class));
    new ProcessWebhookJob($payload, $mapping, 'repo:push')->handle(app(TicketService::class));

    expect(Event::where('event_type_id', 'rebase')->count())->toBe(1);
});

it('creates events for new commits alongside a rebase event for known ones', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $mapping = pushMapping();
    $firstPush = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
    ]);
    $secondPush = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
        ['message' => 'fix phpstan', 'date' => '2026-06-05T10:00:00+00:00'],
    ]);

    new ProcessWebhookJob($firstPush, $mapping, 'repo:push')->handle(app(TicketService::class));
    new ProcessWebhookJob($secondPush, $mapping, 'repo:push')->handle(app(TicketService::class));

    expect(Event::where('event_type_id', 'commit_pushed')->count())->toBe(2)
        ->and(Event::where('event_type_id', 'rebase')->count())->toBe(1);
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --compact tests/Integration/Bitbucket/ProcessPushWebhookJobTest.php`
Expected: first test PASSES (existing behaviour), the other three FAIL (duplicate `commit_pushed` events, no `rebase` events).

- [ ] **Step 4: Implement detection in `ProcessWebhookJob`**

Replace `handlePush()` (lines 47–56) and add three private methods after `createEvent()`:

```php
private function handlePush(TicketService $ticketService): void
{
    foreach ($this->payload['push']['changes'] ?? [] as $change) {
        $branchName = is_string($change['new']['name'] ?? null) ? $change['new']['name'] : null;

        $knownCommits = [];
        foreach ($change['commits'] ?? [] as $commit) {
            if ($this->isKnownCommit($commit)) {
                $knownCommits[] = $commit;

                continue;
            }

            $this->createEventFromCommit($commit, $branchName, $ticketService);
        }

        if ($knownCommits !== []) {
            $this->createRebaseEvent($knownCommits, $branchName);
        }
    }
}

/** @param array<string, mixed> $commit */
private function isKnownCommit(array $commit): bool
{
    $email = $this->extractEmail($commit['author']['raw'] ?? '');
    $date = $commit['date'] ?? null;

    if ($email === null || ! is_string($date)) {
        return false;
    }

    $user = User::where('email', $email)->first();

    if ($user === null) {
        return false;
    }

    [$commitTitle] = $this->splitCommitMessage((string) ($commit['message'] ?? ''));

    return $this->eventExists($user, 'commit_pushed', $commitTitle, Carbon::parse($date)->utc());
}

/** @param non-empty-list<array<string, mixed>> $knownCommits */
private function createRebaseEvent(array $knownCommits, ?string $branchName): void
{
    $email = $this->extractEmail($knownCommits[0]['author']['raw'] ?? '');
    $user = $email === null ? null : User::where('email', $email)->first();

    if ($user === null) {
        return;
    }

    $timestamp = collect($knownCommits)
        ->map(fn (array $commit) => Carbon::parse($commit['date'])->utc())
        ->max();

    $title = sprintf('Rebased %d commits on %s', count($knownCommits), $branchName ?? 'unknown branch');

    if ($this->eventExists($user, 'rebase', $title, $timestamp)) {
        return;
    }

    $this->createEvent(
        user: $user,
        eventTypeId: 'rebase',
        title: $title,
        timestamp: $timestamp,
        ticket: null,
    );
}

private function eventExists(User $user, string $eventTypeId, string $title, Carbon $timestamp): bool
{
    return Event::query()
        ->where('user_id', $user->id)
        ->where('source_id', ServiceProvider::SOURCE_ID)
        ->where('event_type_id', $eventTypeId)
        ->where('started_at', $timestamp)
        ->where('title', mb_substr($title, 0, 255))
        ->exists();
}
```

Note: `rebase` events pass `ticket: null`, so `createEvent()`'s existing guard means no rebase event is created for repositories whose mapping has no customer — acceptable, since such repositories create no commit events either.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Integration/Bitbucket/`
Expected: PASS (new file 4 tests, plus the existing PR tests untouched).

- [ ] **Step 6: Lint, analyse, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan --error-format=json --no-progress
git add integrations/bitbucket tests/Integration/Bitbucket/ProcessPushWebhookJobTest.php
git commit -m "feat: detect rebased commits in bitbucket pushes instead of duplicating events"
```

---

### Task 5: Local proof run (manual verification)

No code. Run against the local example database.

- [ ] **Step 1: Run the new migration**

```bash
php artisan migrate
```
Expected: `seed_rebase_event_type` migration runs.

- [ ] **Step 2: Clean existing duplicate events/activities once**

Inspect first:

```sql
SELECT e.id, e2.id AS duplicate_of, e.title, e.started_at
FROM events e
JOIN events e2 ON e.user_id = e2.user_id AND e.source_id = e2.source_id
    AND e.event_type_id = e2.event_type_id AND e.title = e2.title
    AND e.started_at = e2.started_at AND e.id > e2.id;
```

Then delete the duplicate events and the activities they leave orphaned (events FK sets `activity_id` null on event delete; an activity without events is a leftover duplicate):

```sql
DELETE e FROM events e
JOIN events e2 ON e.user_id = e2.user_id AND e.source_id = e2.source_id
    AND e.event_type_id = e2.event_type_id AND e.title = e2.title
    AND e.started_at = e2.started_at AND e.id > e2.id;

DELETE a FROM activities a
LEFT JOIN events e ON e.activity_id = a.id
WHERE e.id IS NULL;
```

- [ ] **Step 3: Rebundle and inspect**

```bash
php artisan timatic:rebundle-suggestions
```

Verify in the database (or UI):

```sql
SELECT es.id, es.date, es.ticket_number, es.customer_id, COUNT(a.id) AS activity_count
FROM entry_suggestions es
LEFT JOIN activities a ON a.entry_suggestion_id = es.id
WHERE es.deleted_at IS NULL
GROUP BY es.id ORDER BY es.date DESC;
```

Expected shape from the known example data: the six EB-466 commit activities of 2026-06-02 form one suggestion; EB-431 its own; the no-ticket commits of customer 3 that day one no-ticket suggestion; each calendar activity of 2026-06-04 with a distinct ticket (PIO-12, ISO-131, PIO-4) keeps its own suggestion, with the two PIO-12 blocks stacked together.

- [ ] **Step 4: Judge the result**

Bundles that look wrong → capture as a new Pest case in `tests/Integration/Services/SuggestionBundlerTest.php`, adjust rules, re-run `php artisan timatic:rebundle-suggestions` (idempotent). Bundles good → enable `BUILD_STACKED_SUGGESTIONS=true` in local `.env`.

- [ ] **Step 5: Full verification**

```bash
composer prepare-commit
```
Expected: pint, phpstan level 8, and full Pest suite all pass.
