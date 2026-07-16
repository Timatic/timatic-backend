# Suggestion Bundling — Design

**Date:** 2026-07-16
**Status:** Approved design, pending implementation plan

## Context

The pipeline `Event → Activity → EntrySuggestion → Entry` exists but activity-to-suggestion
stacking is unproven and disabled: the `timatic.feature.build_stacked_suggestions` flag is off,
so every activity gets its own suggestion (local data: 397 activities → 397 suggestions, 1:1).

Current implementation:

- `EventCreated` → `App\Listeners\CreateActivity` merges adjacent events (15-minute window,
  same user/source/event type/customer/ticket) into an Activity.
- `ActivityCreated` → `App\Listeners\CreateSuggestion` creates (or, behind the flag, stacks onto)
  an EntrySuggestion. Matching in `findMergeableSuggestion()`: same user + customer + calendar
  date + ticket_number equal **or either null**.
- Suggestion → Entry is client-driven: `POST /entries` with `entrySuggestionId`;
  `EntrySuggestion` `hasOne` Entry.

Problems found in local example data:

1. **Order-dependent bundling** — null ticket_number matches any suggestion, so no-ticket
   activities are absorbed into whichever same-customer/day suggestion existed first.
2. **Duplicate activities** — the same commit pushed again after a rebase creates a duplicate
   `commit_pushed` event (new SHA, same title and commit date), e.g. activity ids 382/386,
   383/387, 369/388.
3. **No way to re-run bundling** — the flow is event-driven only; existing activities already
   hold suggestion links, so rules cannot be re-proven against real data.

## Goals

- Prove bundling works against real local example data, repeatably, as rules evolve.
- Make bundling deterministic (no order-dependence).
- Stop rebase re-pushes from creating duplicate events, at the Bitbucket integration level.

## Decisions made

| Question | Decision |
| --- | --- |
| Primary goal | Prove + fix activity→suggestion bundling |
| Validation | Replay command + Pest tests (both exercise the same service) |
| Ticket matching | Strict: same ticket bundles; null matches only null |
| Duplicates | Detect rebase in the Bitbucket integration, not generic dedupe |
| Entry storage (one entry per activity vs per suggestion) | Deferred — decide after bundling is proven with real bundle shapes |
| Enrichment (PR backfills ticket onto earlier events) | Dropped from this design; may be covered later by event-type relations |
| Event-type relations | Phase 2, drives Event→Activity merging |

## Phasing

1. **Phase 1 (this design's build target):** recomputable bundling — `SuggestionBundler`
   service, strict ticket rules, rebase detection, rebundle command, tests.
2. **Phase 2:** event-type relations for smarter Event→Activity merging.
3. **Later (not designed here):** enrichment, entry-storage decision.

## Phase 1 — Recomputable bundling

### `App\Services\SuggestionBundler`

Single home for the matching logic, used by both the live listener and the replay command so
the replay proves the exact production path.

- `bundle(Activity $activity): EntrySuggestion` — finds a matching open suggestion or creates
  one, attaches the activity.
- Match rules — a suggestion matches when **all** are equal:
  - `user_id`
  - `customer_id`
  - `budget_id`
  - calendar date (of the activity's `started_at`)
  - `is_internal`
  - `ticket_number`, strictly: equal values match, null matches only null.
- Never reuses: soft-deleted suggestions (rejected) or suggestions with an entry (accepted).
  A new suggestion is created instead.
- `CreateSuggestion` listener becomes a thin wrapper delegating to the service.
  The `build_stacked_suggestions` flag is kept as a rollout toggle: off = current 1:1
  behaviour, on = stacking via the service.

### `php artisan timatic:rebundle-suggestions`

Dev/proof tool; the same mechanism later serves enrichment-triggered rebundling.

- Deletes suggestions that have no entry and are not soft-deleted, detaching their activities.
- Replays the detached activities **chronologically** through `SuggestionBundler`
  (chronological order makes results deterministic).
- Scope options: `--user=`, `--from=`, `--to=` (dates).
- Never touched: accepted suggestions (with entry), rejected suggestions (soft-deleted),
  and the activities attached to either.

### Rebase detection (Bitbucket integration)

Facts: the `repo:push` webhook payload contains `commit.hash` (currently unused),
`commit.date` (persisted as `started_at`/`ended_at`), and `change.forced` — Bitbucket's
force-push flag, currently never read. Commit title and commit date survive a rebase; the
SHA does not.

Changes in `ProcessWebhookJob::handlePush` (package `integrations/bitbucket`):

- **Known-commit detection:** a commit is already ingested when an Event exists with the same
  user + bitbucket source + `started_at` equal to `commit.date` + title equal to the commit
  message title.
- **Force-push signal:** read `change.forced` as corroboration.
- **Behaviour:** known commits in a push create no duplicate `commit_pushed` events; instead
  one `rebase` event (new event type, seeded in the bitbucket integration, low weight, no
  ticket claim) is created for the push. Unknown commits in the same push still create normal
  `commit_pushed` events.
- `rebase` events are deliberately weak: they mark branch maintenance and must not spawn
  meaningful suggestions on their own.
- Implementation constraint: work inside `/integrations/bitbucket` requires activating the
  `/timatic-integration` skill first.

### Local data cleanup

Existing duplicates in the local example database are cleaned once (same detection key:
user + source + title + `started_at`) before the rebundle proof; otherwise stacks are inflated.

## Phase 2 — Event-type relations

One event type usually leads to the next (IDE work → commit → PR). Today
`CreateActivity::canBeMergedWithAdjacentActivity()` requires an exact `event_type_id` match,
so an IDE session and the commit that ends it never merge into one activity.

- New table `event_type_relations` (`predecessor_event_type_id`, `successor_event_type_id`)
  with relations on the `EventType` model.
- `CreateActivity::canBeMergedWithAdjacentActivity()` accepts a merge when the types are equal
  **or** related via this table.
- The merged activity takes the higher-weight event type (the weight mechanism already exists
  in overlap handling).
- Relations are seeded (hardcoded seeder); a Filament management UI only if needed later.

## Verification

### Phase 1

- Pest feature tests on `SuggestionBundler` directly:
  - same-ticket activities stack into one suggestion;
  - null ticket only matches null ticket;
  - different budgets never bundle;
  - rejected (soft-deleted) suggestion is not reused;
  - accepted suggestion (has entry) is not reused or modified;
  - chronological replay of a fixed activity set is deterministic.
- Pest test for rebase detection: same commit (title + date) delivered twice via webhook
  payload → one `commit_pushed` event plus one `rebase` event, no duplicate.
- **Proof run:** `timatic:rebundle-suggestions` against local example data; inspect resulting
  bundles in DB/UI. Odd cases become new tests.
- `composer prepare-commit` green.

### Phase 2

- Pest tests: adjacent events with related types merge into one activity; unrelated types do
  not; merged activity carries the higher-weight type.
- Re-run the Phase 1 proof to confirm bundles improve (IDE + commit activities now combined).

## Out of scope

- Entry storage granularity (one entry per activity vs per suggestion) — revisit with real
  bundle shapes after Phase 1.
- Enrichment (backfilling tickets from later PR events onto earlier events).
- Changes to the client accept flow (`POST /entries` with `entrySuggestionId`).
