<?php

use App\Models\EventType;
use App\Models\Source;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * A meeting the user actually sat in is stronger evidence than the calendar item (75) that
     * planned it, so it gets the lower weight and claims its time first.
     */
    public function up(): void
    {
        Source::firstOrCreate(['id' => 'google_meet'], ['title' => 'Google Meet']);

        EventType::query()->updateOrCreate(['id' => 'meeting_attended'], ['name' => 'Meeting attended', 'weight' => 60]);
    }

    public function down(): void
    {
        Source::where('id', 'google_meet')->delete();
        EventType::where('id', 'meeting_attended')->delete();
    }
};
