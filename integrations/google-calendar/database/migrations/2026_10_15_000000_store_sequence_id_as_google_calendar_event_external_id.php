<?php

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Timatic\GoogleCalendar\ServiceProvider;

/**
 * Instances of a recurring Google Calendar event have an id of the form
 * "{recurringEventId}_{originalStartTime}". Strip that suffix so existing
 * events carry their sequence id, the same way newly synced events do.
 */
return new class extends Migration
{
    private const INSTANCE_ID_PATTERN = '/^(.+)_\d{8}(T\d{6}Z)?$/';

    public function up(): void
    {
        Event::query()
            ->where('source_id', ServiceProvider::SOURCE_ID)
            ->whereNotNull('external_id')
            ->chunkById(500, function (Collection $events): void {
                foreach ($events as $event) {
                    if (preg_match(self::INSTANCE_ID_PATTERN, (string) $event->external_id, $matches)) {
                        Event::query()->whereKey($event->id)->update(['external_id' => $matches[1]]);
                    }
                }
            });
    }

    public function down(): void
    {
        // The original instance ids can't be restored; sequence ids are a valid external_id too.
    }
};
