<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    /** @var array<string, array{name: string, weight: int}> */
    private array $eventTypes = [
        'browser_focus' => ['name' => 'Browser activity', 'weight' => 100],
        'meeting_attended' => ['name' => 'Meeting attended', 'weight' => 60],
    ];

    public function run(): void
    {
        foreach ($this->eventTypes as $id => $attributes) {
            EventType::query()->updateOrCreate(['id' => $id], $attributes);
        }
    }
}
