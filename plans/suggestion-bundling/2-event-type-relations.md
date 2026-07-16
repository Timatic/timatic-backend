# Phase 2: Event-Type Relations — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Spec:** `docs/superpowers/specs/2026-07-16-suggestion-bundling-design.md` (Phase 2 section)
**Depends on:** Phase 1 (`plans/suggestion-bundling/1-recomputable-bundling.md`) completed — the proof re-run in Task 4 uses `timatic:rebundle-suggestions`.

**Goal:** Let adjacent events of *related* types (one usually leads to the next, e.g. commit → PR activity) merge into one Activity instead of requiring an exact event-type match.

**Architecture:** A self-referencing pivot `event_type_relations` (predecessor → successor) on `EventType`. `CreateActivity::canBeMergedWithAdjacentActivity()` consults it; a merged activity adopts the higher-weight event type. Relations are seeded via a migration, following the existing pattern of seeding event types in migrations.

**Tech Stack:** Laravel 12, PHP 8.4, Pest 3, PHPStan level 8 (Larastan), Pint.

## Global Constraints

- Run `vendor/bin/pint --dirty --format agent` after modifying any PHP file.
- Before each commit: `composer prepare-commit` must pass. Quick loops: `vendor/bin/phpstan --error-format=json --no-progress`, `php artisan test --compact --filter=<name>`.
- Commit messages: Conventional Commits, imperative, lowercase, no AI attribution trailers.
- Code style: public methods first; no inline comments except constraints the code cannot show; explicit types everywhere.
- Tests: Pest, atomic, `RefreshDatabase`, `Illuminate\Support\Facades\Event::fake()` when model creation must not trigger the queued listeners.
- Known limitation (unchanged from spec, do not "fix" in passing): activity merging still requires an equal **non-null** `ticket_number`, so related-type merging only applies to ticketed events. Relaxing that is a future decision.

---

### Task 1: Fix `Activity::eventType()` relation

**Files:**
- Modify: `app/Models/Activity.php:87-93`

**Interfaces:**
- Consumes: existing `activities.event_type_id` FK column.
- Produces: `Activity::eventType(): BelongsTo` — Tasks 2–3 rely on `$activity->eventType` loading via `event_type_id`.

`Activity::eventType()` is declared `HasOne<EventType>`, which queries `event_types.activity_id` — a column that does not exist. The relation errors whenever it is actually loaded (only reachable today when `activity_overlap_detection` is enabled). The FK lives on `activities.event_type_id`, so it is a `BelongsTo`, exactly like `Event::eventType()`.

- [ ] **Step 1: Change the relation**

In `app/Models/Activity.php`, replace:

```php
    /**
     * @return HasOne<EventType, $this>
     */
    public function eventType(): HasOne
    {
        return $this->hasOne(EventType::class);
    }
```

with:

```php
    /**
     * @return BelongsTo<EventType, $this>
     */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }
```

Remove the now-unused `use Illuminate\Database\Eloquent\Relations\HasOne;` import (keep it if still used elsewhere in the file — it is also used by nothing else after this change).

- [ ] **Step 2: Verify with a test**

Add to `tests/Integration/Activity/CreateActivityTest.php`:

```php
it('loads the event type of an activity', function () {
    Illuminate\Support\Facades\Event::fake();
    $eventType = App\Models\EventType::firstOrCreate(['id' => 'ticket_saved'], ['weight' => 1]);

    $activity = App\Models\Activity::factory()->create(['event_type_id' => $eventType->id]);

    expect($activity->eventType)->toBeInstanceOf(App\Models\EventType::class)
        ->and($activity->eventType->id)->toBe('ticket_saved');
});
```

Run: `php artisan test --compact --filter="loads the event type of an activity"`
Expected: PASS (would throw an unknown-column SQL error with the old `HasOne`).

- [ ] **Step 3: Run affected suites, lint, analyse, commit**

```bash
php artisan test --compact tests/Integration/Activity
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan --error-format=json --no-progress
git add app/Models/Activity.php tests/Integration/Activity/CreateActivityTest.php
git commit -m "fix: Activity::eventType is a belongsTo, not a hasOne"
```

---

### Task 2: `event_type_relations` table and `EventType` API

**Files:**
- Create: migration `database/migrations/<timestamp>_create_event_type_relations_table.php` (via `php artisan make:migration create_event_type_relations_table --no-interaction`)
- Modify: `app/Models/EventType.php`
- Test: `tests/Unit/EventTypeRelationTest.php`

**Interfaces:**
- Produces: `EventType::successors(): BelongsToMany<EventType>` and `EventType::leadsTo(string $eventTypeId): bool`. Task 3 calls `leadsTo()`; Task 4 inserts into `event_type_relations`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/EventTypeRelationTest.php`:

```php
<?php

use App\Models\EventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('knows which event types it leads to', function () {
    $commit = EventType::create(['id' => 'commit_pushed', 'weight' => 70]);
    EventType::create(['id' => 'pr_merged', 'weight' => 70]);
    EventType::create(['id' => 'calendar_event_started', 'weight' => 50]);

    DB::table('event_type_relations')->insert([
        'predecessor_event_type_id' => 'commit_pushed',
        'successor_event_type_id' => 'pr_merged',
    ]);

    expect($commit->leadsTo('pr_merged'))->toBeTrue()
        ->and($commit->leadsTo('calendar_event_started'))->toBeFalse()
        ->and($commit->successors()->pluck('id')->all())->toBe(['pr_merged']);
});

it('does not treat a relation as symmetric', function () {
    EventType::create(['id' => 'commit_pushed', 'weight' => 70]);
    $merged = EventType::create(['id' => 'pr_merged', 'weight' => 70]);

    DB::table('event_type_relations')->insert([
        'predecessor_event_type_id' => 'commit_pushed',
        'successor_event_type_id' => 'pr_merged',
    ]);

    expect($merged->leadsTo('commit_pushed'))->toBeFalse();
});
```

Run: `php artisan test --compact tests/Unit/EventTypeRelationTest.php`
Expected: FAIL — table `event_type_relations` does not exist.

- [ ] **Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_type_relations', function (Blueprint $table) {
            $table->string('predecessor_event_type_id');
            $table->string('successor_event_type_id');

            $table->primary(['predecessor_event_type_id', 'successor_event_type_id']);
            $table->foreign('predecessor_event_type_id')->references('id')->on('event_types')->cascadeOnDelete();
            $table->foreign('successor_event_type_id')->references('id')->on('event_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_type_relations');
    }
};
```

- [ ] **Step 3: Add the relation API to `EventType`**

In `app/Models/EventType.php`, add after `events()` (imports: `Illuminate\Database\Eloquent\Relations\BelongsToMany`):

```php
    /**
     * @return BelongsToMany<EventType, $this>
     */
    public function successors(): BelongsToMany
    {
        return $this->belongsToMany(
            EventType::class,
            'event_type_relations',
            'predecessor_event_type_id',
            'successor_event_type_id',
        );
    }

    public function leadsTo(string $eventTypeId): bool
    {
        return $this->successors()->whereKey($eventTypeId)->exists();
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Unit/EventTypeRelationTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Lint, analyse, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan --error-format=json --no-progress
git add database/migrations/*create_event_type_relations_table.php app/Models/EventType.php tests/Unit/EventTypeRelationTest.php
git commit -m "feat: relate event types as predecessor and successor"
```

---

### Task 3: Merge adjacent events of related types into one Activity

**Files:**
- Modify: `app/Listeners/CreateActivity.php` (`handle()` merge branch at lines 37–46 and `canBeMergedWithAdjacentActivity()` at lines 52–64)
- Test: `tests/Integration/Activity/CreateActivityTest.php` (add cases; do not rewrite existing tests)

**Interfaces:**
- Consumes: `EventType::leadsTo(string): bool` from Task 2; `Activity::eventType(): BelongsTo` from Task 1.
- Produces: unchanged listener contract; behaviour extension only.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Integration/Activity/CreateActivityTest.php` (follow the file's existing style for creating events and invoking the listener — read it first; the essential shape below uses the listener directly like `CreateSuggestionTest` does):

```php
it('merges an adjacent event of a related type into the activity', function () {
    Illuminate\Support\Facades\Event::fake();
    App\Models\EventType::firstOrCreate(['id' => 'commit_pushed'], ['weight' => 70]);
    App\Models\EventType::firstOrCreate(['id' => 'pr_merged'], ['weight' => 70]);
    Illuminate\Support\Facades\DB::table('event_type_relations')->insert([
        'predecessor_event_type_id' => 'commit_pushed',
        'successor_event_type_id' => 'pr_merged',
    ]);

    $user = App\Models\User::factory()->create();
    $source = App\Models\Source::factory()->create();

    $commitEvent = App\Models\Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'event_type_id' => 'commit_pushed',
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 09:00:00',
    ]);
    app(App\Listeners\CreateActivity::class)->handle(new App\Events\EventCreated($commitEvent));

    $prEvent = App\Models\Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'event_type_id' => 'pr_merged',
        'started_at' => '2026-06-04 09:05:00',
        'ended_at' => '2026-06-04 09:05:00',
    ]);
    app(App\Listeners\CreateActivity::class)->handle(new App\Events\EventCreated($prEvent));

    expect(App\Models\Activity::count())->toBe(1)
        ->and($commitEvent->fresh()->activity_id)->toBe($prEvent->fresh()->activity_id);
});

it('does not merge adjacent events of unrelated types', function () {
    Illuminate\Support\Facades\Event::fake();
    App\Models\EventType::firstOrCreate(['id' => 'commit_pushed'], ['weight' => 70]);
    App\Models\EventType::firstOrCreate(['id' => 'calendar_event_started'], ['weight' => 50]);

    $user = App\Models\User::factory()->create();
    $source = App\Models\Source::factory()->create();

    $commitEvent = App\Models\Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'event_type_id' => 'commit_pushed',
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 09:00:00',
    ]);
    app(App\Listeners\CreateActivity::class)->handle(new App\Events\EventCreated($commitEvent));

    $calendarEvent = App\Models\Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'event_type_id' => 'calendar_event_started',
        'started_at' => '2026-06-04 09:05:00',
        'ended_at' => '2026-06-04 09:05:00',
    ]);
    app(App\Listeners\CreateActivity::class)->handle(new App\Events\EventCreated($calendarEvent));

    expect(App\Models\Activity::count())->toBe(2);
});

it('adopts the higher-weight event type when merging related types', function () {
    Illuminate\Support\Facades\Event::fake();
    App\Models\EventType::firstOrCreate(['id' => 'pr_commented'], ['weight' => 40]);
    App\Models\EventType::firstOrCreate(['id' => 'pr_merged'], ['weight' => 70]);
    Illuminate\Support\Facades\DB::table('event_type_relations')->insert([
        'predecessor_event_type_id' => 'pr_commented',
        'successor_event_type_id' => 'pr_merged',
    ]);

    $user = App\Models\User::factory()->create();
    $source = App\Models\Source::factory()->create();

    $commentEvent = App\Models\Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'event_type_id' => 'pr_commented',
        'started_at' => '2026-06-04 09:00:00',
        'ended_at' => '2026-06-04 09:00:00',
    ]);
    app(App\Listeners\CreateActivity::class)->handle(new App\Events\EventCreated($commentEvent));

    $mergeEvent = App\Models\Event::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'customer_id' => '1',
        'ticket_number' => 'PIO-12',
        'event_type_id' => 'pr_merged',
        'started_at' => '2026-06-04 09:05:00',
        'ended_at' => '2026-06-04 09:05:00',
    ]);
    app(App\Listeners\CreateActivity::class)->handle(new App\Events\EventCreated($mergeEvent));

    $activity = App\Models\Activity::sole();
    expect($activity->event_type_id)->toBe('pr_merged');
});
```

Run: `php artisan test --compact tests/Integration/Activity/CreateActivityTest.php`
Expected: the three new tests FAIL (two activities created where one expected; type not upgraded).

- [ ] **Step 2: Implement in `CreateActivity`**

In `app/Listeners/CreateActivity.php`, replace `canBeMergedWithAdjacentActivity()`:

```php
    private function canBeMergedWithAdjacentActivity(Activity $lastActivity, Event $event): bool
    {
        $suggestion = $lastActivity->entrySuggestion;

        if (is_null($event->customer_id)) {
            return false;
        }

        return $this->haveCompatibleEventTypes($lastActivity, $event)
            && $lastActivity->customer_id === $event->customer_id
            && ($lastActivity->ticket_number === $event->ticket_number && $event->ticket_number !== null)
            && ($suggestion === null || $suggestion->trashed() === false);
    }

    private function haveCompatibleEventTypes(Activity $lastActivity, Event $event): bool
    {
        if ($lastActivity->event_type_id === $event->event_type_id) {
            return true;
        }

        if ($lastActivity->eventType === null || $event->event_type_id === null) {
            return false;
        }

        return $lastActivity->eventType->leadsTo($event->event_type_id);
    }
```

And in `handle()`, extend the merge branch (currently lines 37–46) to adopt the higher-weight type before saving:

```php
        if ($adjacentActivity && $this->canBeMergedWithAdjacentActivity($adjacentActivity, $event)) {
            $startedAt = $adjacentActivity->started_at;
            if ($event->started_at) {
                $startedAt = $adjacentActivity->started_at->min($event->started_at);
            }

            $adjacentActivity->events()->save($event);
            $adjacentActivity->started_at = $startedAt;
            $adjacentActivity->ended_at = $event->ended_at->max($adjacentActivity->ended_at);

            if (($event->eventType?->weight ?? 0) > ($adjacentActivity->eventType?->weight ?? 0)) {
                $adjacentActivity->event_type_id = $event->event_type_id;
            }

            $adjacentActivity->save();
        } else {
            $this->createActivityFromEvent($event);
        }
```

- [ ] **Step 3: Run tests to verify they pass**

Run: `php artisan test --compact tests/Integration/Activity/CreateActivityTest.php`
Expected: PASS, including all pre-existing tests in the file.

- [ ] **Step 4: Lint, analyse, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan --error-format=json --no-progress
git add app/Listeners/CreateActivity.php tests/Integration/Activity/CreateActivityTest.php
git commit -m "feat: merge adjacent events of related types into one activity"
```

---

### Task 4: Seed initial relations

**Files:**
- Create: migration `database/migrations/<timestamp>_seed_initial_event_type_relations.php` (via `php artisan make:migration seed_initial_event_type_relations --no-interaction`)

**Interfaces:**
- Consumes: `event_type_relations` table from Task 2; event types seeded by the bitbucket integration migrations (dated 2026_04, so they sort before this one).

Seeding via migration follows the existing pattern (`seed_bitbucket_pr_event_types`). The initial list covers the Bitbucket chain; extend it as more integrations gain related types. Relations are only inserted when both types exist, so the migration is safe on installations without the bitbucket integration.

- [ ] **Step 1: Write the migration**

```php
<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<array{string, string}> */
    private const array RELATIONS = [
        ['commit_pushed', 'pr_commented'],
        ['commit_pushed', 'pr_approved'],
        ['commit_pushed', 'pr_changes_requested'],
        ['commit_pushed', 'pr_merged'],
        ['pr_commented', 'pr_merged'],
        ['pr_approved', 'pr_merged'],
    ];

    public function up(): void
    {
        foreach (self::RELATIONS as [$predecessor, $successor]) {
            if (EventType::whereKey($predecessor)->exists() && EventType::whereKey($successor)->exists()) {
                DB::table('event_type_relations')->insertOrIgnore([
                    'predecessor_event_type_id' => $predecessor,
                    'successor_event_type_id' => $successor,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::RELATIONS as [$predecessor, $successor]) {
            DB::table('event_type_relations')
                ->where('predecessor_event_type_id', $predecessor)
                ->where('successor_event_type_id', $successor)
                ->delete();
        }
    }
};
```

- [ ] **Step 2: Run it and verify**

```bash
php artisan migrate
```

Then check: `php artisan tinker --execute 'dump(DB::table("event_type_relations")->count());'`
Expected: 6 (on a database with the bitbucket integration migrated).

- [ ] **Step 3: Lint, analyse, commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan --error-format=json --no-progress
git add database/migrations/*seed_initial_event_type_relations.php
git commit -m "feat: seed initial event type relations for the bitbucket chain"
```

---

### Task 5: Re-run the bundling proof (manual verification)

No code.

- [ ] **Step 1: Rebundle with the new merging in place**

New activities formed after this phase benefit from related-type merging; existing activities keep their event grouping (activity re-formation from events is out of scope). Re-run the suggestion proof to confirm nothing regressed:

```bash
php artisan timatic:rebundle-suggestions
```

Inspect as in Phase 1 Task 5. Expected: identical or better bundles; no suggestion loses activities.

- [ ] **Step 2: Full verification**

```bash
composer prepare-commit
```
Expected: pint, phpstan level 8, and the full Pest suite pass.
