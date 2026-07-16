<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $eventType = EventType::firstOrNew(['id' => 'rebase']);
        $eventType->weight = 10;
        $eventType->save();
    }

    public function down(): void
    {
        EventType::where('id', 'rebase')->delete();
    }
};
