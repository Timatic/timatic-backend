<?php

namespace App\Queries;

use App\Models\Entry;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PreviousBookingsOfSequences
{
    /**
     * Entries the user booked on suggestions containing an event of the same
     * sequence (source + external id) as one of the given events, latest first.
     *
     * @param  Collection<int, Event>  $events
     * @return Builder<Entry>
     */
    public static function query(int $userId, Collection $events): Builder
    {
        return Entry::query()
            ->select(['events.source_id', 'events.external_id', 'entries.customer_id', 'entries.budget_id', 'entries.is_internal', 'entries.started_at'])
            ->join('activities', 'activities.entry_suggestion_id', '=', 'entries.entry_suggestion_id')
            ->join('activity_event', 'activity_event.activity_id', '=', 'activities.id')
            ->join('events', 'events.id', '=', 'activity_event.event_id')
            ->where('entries.user_id', $userId)
            ->where(function (Builder $query) use ($events) {
                foreach ($events as $event) {
                    $query->orWhere(fn (Builder $query) => $query
                        ->where('events.source_id', $event->source_id)
                        ->where('events.external_id', $event->external_id));
                }
            })
            ->latest('entries.started_at');
    }
}
