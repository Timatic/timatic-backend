<?php

use App\Models\EventType;
use App\Models\Source;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Source::firstOrCreate(['id' => 'bitbucket'], ['title' => 'Bitbucket']);

        $eventType = EventType::firstOrNew(['id' => 'commit_pushed']);
        $eventType->weight = 70;
        $eventType->save();
    }

    public function down(): void
    {
        EventType::where('id', 'commit_pushed')->delete();
        Source::where('id', 'bitbucket')->delete();
    }
};
