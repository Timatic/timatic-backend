<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $eventTypes = [
            'pr_commented' => 40,
            'pr_approved' => 50,
            'pr_changes_requested' => 50,
            'pr_merged' => 70,
            'pr_declined' => 50,
        ];

        foreach ($eventTypes as $id => $weight) {
            $eventType = EventType::firstOrNew(['id' => $id]);
            $eventType->weight = $weight;
            $eventType->save();
        }
    }

    public function down(): void
    {
        EventType::whereIn('id', ['pr_commented', 'pr_approved', 'pr_changes_requested', 'pr_merged', 'pr_declined'])->delete();
    }
};
