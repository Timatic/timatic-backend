<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;
use Timatic\GoogleCalendar\ServiceProvider;

return new class extends Migration
{
    public function up(): void
    {
        EventType::query()
            ->whereKey(ServiceProvider::EVENT_TYPE_CALENDAR_EVENT_STARTED)
            ->update(['name' => 'Calendar item']);
    }

    public function down(): void
    {
        EventType::query()
            ->whereKey(ServiceProvider::EVENT_TYPE_CALENDAR_EVENT_STARTED)
            ->update(['name' => null]);
    }
};
