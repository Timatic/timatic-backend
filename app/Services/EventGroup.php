<?php

namespace App\Services;

use App\DataTransferObjects\Period;
use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EventGroup
{
    /** @var Collection<int, Event> */
    public Collection $events;

    public CarbonInterface $startedAt;

    public CarbonInterface $endedAt;

    public function __construct(
        public readonly ?string $customerId,
        public readonly ?string $ticketNumber,
        public readonly ?string $eventTypeId,
        Event $event,
        CarbonInterface $effectiveStart,
    ) {
        $this->events = collect([$event]);
        $this->startedAt = $effectiveStart;
        $this->endedAt = $event->ended_at;
    }

    public function add(Event $event, CarbonInterface $effectiveStart): void
    {
        $this->events->push($event);
        $this->startedAt = $this->startedAt->min($effectiveStart);
        $this->endedAt = $this->endedAt->max($event->ended_at);
    }

    public function period(): Period
    {
        return new Period($this->startedAt, $this->endedAt);
    }
}
