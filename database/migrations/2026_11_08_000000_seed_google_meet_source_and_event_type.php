<?php

use App\Models\EventType;
use App\Models\Source;
use Database\Seeders\EventTypeSeeder;
use Database\Seeders\SourceSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The data lives in the seeders; this only makes sure a deployment has the google_meet rows.
     * Both seeders are idempotent, so rerunning them leaves the other rows as they are.
     */
    public function up(): void
    {
        (new SourceSeeder)->run();
        (new EventTypeSeeder)->run();
    }

    public function down(): void
    {
        Source::where('id', 'google_meet')->delete();
        EventType::where('id', 'meeting_attended')->delete();
    }
};
