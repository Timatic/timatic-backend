<?php

use App\Events\EventCreated;
use App\Models\Activity;
use App\Models\EntrySuggestion;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('the backfill stores the event sequences of existing suggestions', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $suggestion = EntrySuggestion::factory()->create();
    $meeting = Event::factory()->create(['external_id' => 'weekly-sync']);
    $commit = Event::factory()->create(['external_id' => null]);
    Activity::factory()->create(['entry_suggestion_id' => $suggestion->id])->events()->attach([$meeting->id, $commit->id]);
    Activity::factory()->create(['entry_suggestion_id' => $suggestion->id])->events()->attach($meeting);

    (require database_path('migrations/2026_11_08_000001_backfill_entry_suggestion_sequences.php'))->up();

    expect(DB::table('entry_suggestion_sequences')->get(['entry_suggestion_id', 'source_id', 'external_id'])->map(fn (object $row) => (array) $row)->all())
        ->toBe([['entry_suggestion_id' => $suggestion->id, 'source_id' => $meeting->source_id, 'external_id' => 'weekly-sync']]);
});
