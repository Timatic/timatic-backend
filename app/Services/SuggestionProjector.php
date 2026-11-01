<?php

namespace App\Services;

use App\DataTransferObjects\PreviousSequenceBooking;
use App\Models\Activity;
use App\Models\EntrySuggestion;
use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SuggestionProjector
{
    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, EntrySuggestion>  $dismissedSuggestions
     * @param  Collection<int, PreviousSequenceBooking>  $previousSequenceBookings  latest first
     * @return Collection<int, EntrySuggestion>
     */
    public function project(
        Collection $activities,
        Collection $dismissedSuggestions,
        CarbonInterface $date,
        Collection $previousSequenceBookings = new Collection,
    ): Collection {
        return $this->buildSuggestions($activities, $date)
            ->each(function (EntrySuggestion $suggestion) use ($previousSequenceBookings) {
                if ($suggestion->customer_id === null) {
                    $this->bookLikePreviousEntryOfSequence($suggestion, $previousSequenceBookings);
                }
            })
            ->reject(fn (EntrySuggestion $suggestion) => $this->isDismissed($suggestion, $dismissedSuggestions))
            ->values();
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, EntrySuggestion>
     */
    private function buildSuggestions(Collection $activities, CarbonInterface $date): Collection
    {
        [$customerActivities, $customerlessActivities] = $activities
            ->partition(fn (Activity $activity) => $activity->customer_id !== null)
            ->all();

        $customerlessSuggestions = $customerlessActivities->map(fn (Activity $activity) => $this->suggestionStartingWith($activity, $date));

        $customerSuggestions = $customerActivities
            ->groupBy('customer_id')
            ->flatMap(fn (Collection $activitiesOfCustomer) => $this->chainIntoSuggestions($activitiesOfCustomer, $date));

        return $this->mergeSuggestionsSharingAnEvent($customerlessSuggestions->concat($customerSuggestions));
    }

    /**
     * An event split into several activities (e.g. carved up by overlapping
     * work) must still end up in a single suggestion.
     *
     * @param  Collection<int, EntrySuggestion>  $suggestions
     * @return Collection<int, EntrySuggestion>
     */
    private function mergeSuggestionsSharingAnEvent(Collection $suggestions): Collection
    {
        return $suggestions->reduce(function (Collection $mergedSuggestions, EntrySuggestion $suggestion) {
            $eventIds = $suggestion->activities->flatMap->events->pluck('id');

            $suggestionSharingAnEvent = $mergedSuggestions->first(
                fn (EntrySuggestion $mergedSuggestion) => $mergedSuggestion->activities
                    ->flatMap->events
                    ->pluck('id')
                    ->intersect($eventIds)
                    ->isNotEmpty()
            );

            if ($suggestionSharingAnEvent === null) {
                $mergedSuggestions->push($suggestion);
            } else {
                $suggestion->activities->each(fn (Activity $activity) => $suggestionSharingAnEvent->addActivity($activity));
            }

            return $mergedSuggestions;
        }, collect())->values();
    }

    /**
     * Walks the customer's activities in chronological order, adding each
     * one to the preceding suggestion when it chains onto that suggestion.
     *
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, EntrySuggestion>
     */
    private function chainIntoSuggestions(Collection $activities, CarbonInterface $date): Collection
    {
        $suggestions = collect();
        $currentSuggestion = null;

        foreach ($activities->sortBy('started_at') as $activity) {
            if ($currentSuggestion !== null && $this->canChainOnto($activity, $currentSuggestion)) {
                $currentSuggestion->addActivity($activity);

                continue;
            }

            $currentSuggestion = $this->suggestionStartingWith($activity, $date);
            $suggestions->push($currentSuggestion);
        }

        return $suggestions;
    }

    private function suggestionStartingWith(Activity $activity, CarbonInterface $date): EntrySuggestion
    {
        $suggestion = new EntrySuggestion;
        $suggestion->date = $date->toDateString();
        $suggestion->addActivity($activity);

        return $suggestion;
    }

    private function canChainOnto(Activity $activity, EntrySuggestion $suggestion): bool
    {
        if ($suggestion->is_internal !== $activity->is_internal) {
            return false;
        }

        if (! $this->compatible($suggestion->budget_id, $activity->budget_id)) {
            return false;
        }

        if (! $this->compatible($suggestion->ticket_number, $activity->ticket_number)) {
            return false;
        }

        return true;
    }

    /**
     * Two values are compatible for chaining when either is unset, or when
     * both are set and equal.
     */
    private function compatible(int|string|null $a, int|string|null $b): bool
    {
        return $a === null || $b === null || $a === $b;
    }

    /**
     * @param  Collection<int, EntrySuggestion>  $dismissedSuggestions
     */
    private function isDismissed(EntrySuggestion $suggestion, Collection $dismissedSuggestions): bool
    {
        return $dismissedSuggestions->contains(fn (EntrySuggestion $dismissed) => $dismissed->customer_id === $suggestion->customer_id
            && $dismissed->budget_id === $suggestion->budget_id
            && $dismissed->ticket_number === $suggestion->ticket_number
            && $dismissed->is_internal === $suggestion->is_internal);
    }

    /**
     * A suggestion without a customer of its own takes the customer and budget
     * from the last entry booked on an earlier occurrence of the same sequence,
     * e.g. the previous instance of a recurring meeting.
     *
     * @param  Collection<int, PreviousSequenceBooking>  $previousSequenceBookings
     */
    private function bookLikePreviousEntryOfSequence(EntrySuggestion $suggestion, Collection $previousSequenceBookings): void
    {
        $previousBooking = $suggestion->activities
            ->filter(fn (Activity $activity) => $activity->relationLoaded('events'))
            ->flatMap(fn (Activity $activity) => $activity->events)
            ->map(fn (Event $event) => $previousSequenceBookings
                ->where('sourceId', $event->source_id)
                ->where('externalId', $event->external_id)
                ->first())
            ->filter()
            ->sortByDesc('startedAt')
            ->first();

        if ($previousBooking === null) {
            return;
        }

        $suggestion->customer_id = $previousBooking->customerId;
        $suggestion->budget_id = $previousBooking->budgetId;
        $suggestion->is_internal = $previousBooking->isInternal;
    }
}
