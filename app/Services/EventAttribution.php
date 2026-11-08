<?php

namespace App\Services;

use App\DataTransferObjects\PreviousSequenceBooking;
use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * An event without a mapping of its own is booked like the last entry on an
 * earlier occurrence of its sequence, e.g. the previous instance of a recurring
 * meeting. The event itself stays as it came in; only the activity learns.
 */
class EventAttribution
{
    /**
     * @param  Collection<int, PreviousSequenceBooking>  $previousSequenceBookings
     */
    public function __construct(private readonly Collection $previousSequenceBookings) {}

    public function customerId(Event $event): ?string
    {
        return $event->customer_id ?? $this->previousBookingOf($event)?->customerId;
    }

    public function budgetId(Event $event): ?int
    {
        if ($event->budget_id !== null) {
            return $event->budget_id;
        }

        $previousBooking = $this->previousBookingOf($event);

        if ($previousBooking === null || $previousBooking->customerId !== $this->customerId($event)) {
            return null;
        }

        return $previousBooking->budgetId;
    }

    public function isInternal(Event $event): ?bool
    {
        if ($event->customer_id !== null) {
            return $event->is_internal;
        }

        $previousBooking = $this->previousBookingOf($event);

        return $previousBooking === null ? $event->is_internal : $previousBooking->isInternal;
    }

    private function previousBookingOf(Event $event): ?PreviousSequenceBooking
    {
        return $this->previousSequenceBookings
            ->where('sourceId', $event->source_id)
            ->where('externalId', $event->external_id)
            ->sortByDesc('startedAt')
            ->first();
    }
}
