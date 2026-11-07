<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('entry_suggestion_sequences')->insertUsing(
            ['entry_suggestion_id', 'source_id', 'external_id'],
            DB::table('activities')
                ->join('activity_event', 'activity_event.activity_id', '=', 'activities.id')
                ->join('events', 'events.id', '=', 'activity_event.event_id')
                ->whereNotNull('activities.entry_suggestion_id')
                ->whereNotNull('events.external_id')
                ->distinct()
                ->select(['activities.entry_suggestion_id', 'events.source_id', 'events.external_id']),
        );
    }

    public function down(): void
    {
        DB::table('entry_suggestion_sequences')->truncate();
    }
};
