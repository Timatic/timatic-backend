<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        EventType::unguard();

        $eventTypes = [
            'issue_changed_to_done' => 93,
            'issue_worklog_created' => 95,
        ];

        foreach ($eventTypes as $id => $weight) {
            $eventType = EventType::firstOrNew(['id' => $id]);
            $eventType->weight = $weight;
            $eventType->save();
        }

        EventType::reguard();
    }

    public function down(): void
    {
        EventType::whereIn('id', [
            'issue_changed_to_done',
            'issue_worklog_created',
        ])->delete();
    }
};
