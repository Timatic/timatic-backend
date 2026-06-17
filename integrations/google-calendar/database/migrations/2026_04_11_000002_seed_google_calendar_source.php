<?php

use App\Models\EventType;
use App\Models\Source;
use Illuminate\Database\Migrations\Migration;
use Timatic\GoogleCalendar\ServiceProvider;

return new class extends Migration
{
    public function up(): void
    {
        Source::firstOrCreate(
            ['id' => ServiceProvider::SOURCE_ID],
            ['title' => 'Google Calendar'],
        );

        $eventType = EventType::firstOrNew(['id' => ServiceProvider::EVENT_TYPE_CALENDAR_EVENT_STARTED]);
        $eventType->weight = 75;
        $eventType->save();
    }

    public function down(): void
    {
        Source::where('id', ServiceProvider::SOURCE_ID)->delete();
        EventType::where('id', ServiceProvider::EVENT_TYPE_CALENDAR_EVENT_STARTED)->delete();
    }
};
