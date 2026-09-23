<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Browser focus is the weakest evidence there is that work happened, so it carries the highest
     * weight: the activity projector processes events by ascending weight and subtracts time that
     * earlier activities already claimed. Jira, GitHub and calendar activity therefore win, and a
     * browser session only fills what is left.
     *
     * @var array<string, int>
     */
    private array $eventTypes = [
        'browser_focus' => 100,
    ];

    public function up(): void
    {
        foreach ($this->eventTypes as $id => $weight) {
            $eventType = EventType::firstOrNew(['id' => $id]);
            $eventType->weight = $weight;
            $eventType->save();
        }
    }

    public function down(): void
    {
        EventType::whereIn('id', array_keys($this->eventTypes))->delete();
    }
};
