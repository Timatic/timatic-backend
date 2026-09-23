<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    /**
     * The event types this application produces itself; integration packages seed their own.
     *
     * Browser focus is the weakest evidence there is that work happened, so it carries the highest
     * weight: the activity projector processes events by ascending weight and subtracts time that
     * earlier activities already claimed. Jira, GitHub and calendar activity therefore win, and a
     * browser session only fills what is left.
     *
     * @var array<string, array{name: string, weight: int}>
     */
    private array $eventTypes = [
        'browser_focus' => ['name' => 'Browser activity', 'weight' => 100],
    ];

    public function run(): void
    {
        foreach ($this->eventTypes as $id => $attributes) {
            EventType::query()->updateOrCreate(['id' => $id], $attributes);
        }
    }
}
