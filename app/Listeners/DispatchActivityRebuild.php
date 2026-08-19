<?php

namespace App\Listeners;

use App\Events\EventCreated;
use App\Jobs\RebuildUserDay;
use App\Models\Event;

class DispatchActivityRebuild
{
    public function handle(EventCreated $eventCreated): void
    {
        $event = $eventCreated->getEvent();

        foreach ($this->touchedDates($event) as $date) {
            RebuildUserDay::dispatch((int) $event->user_id, $date);
        }
    }

    /**
     * @return list<string>
     */
    private function touchedDates(Event $event): array
    {
        $start = ($event->started_at ?? $event->ended_at->copy()->subMinutes(15))->copy()->startOfDay();
        $end = $event->ended_at->copy();

        $dates = [];
        for ($date = $start; $date->lessThanOrEqualTo($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }
}
