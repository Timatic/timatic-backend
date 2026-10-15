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
        return $this->buildGroups($activities)
            ->map(fn (Collection $group) => $this->suggestionFromActivities($group, $date, $previousSequenceBookings))
            ->reject(fn (EntrySuggestion $suggestion) => $this->isDismissed($suggestion, $dismissedSuggestions))
            ->values();
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, Collection<int, Activity>>
     */
    private function buildGroups(Collection $activities): Collection
    {
        [$customerActivities, $customerlessActivities] = $activities
            ->partition(fn (Activity $activity) => $activity->customer_id !== null)
            ->all();

        $customerlessGroups = $customerlessActivities->map(fn (Activity $activity) => collect([$activity]));

        $customerGroups = $customerActivities
            ->groupBy('customer_id')
            ->flatMap(fn (Collection $activitiesOfCustomer) => $this->chainIntoGroups($activitiesOfCustomer));

        return $customerlessGroups->concat($customerGroups)->values();
    }

    /**
     * Walks the customer's activities in chronological order, attaching each
     * one to the preceding group when it chains onto that group.
     *
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, Collection<int, Activity>>
     */
    private function chainIntoGroups(Collection $activities): Collection
    {
        $groups = collect();
        $currentGroup = null;

        foreach ($activities->sortBy('started_at') as $activity) {
            if ($currentGroup !== null && $this->canChainOnto($activity, $currentGroup)) {
                $currentGroup->push($activity);

                continue;
            }

            $currentGroup = collect([$activity]);
            $groups->push($currentGroup);
        }

        return $groups;
    }

    /**
     * @param  Collection<int, Activity>  $group
     */
    private function canChainOnto(Activity $activity, Collection $group): bool
    {
        $representative = $this->representative($group);

        if ($representative->is_internal !== $activity->is_internal) {
            return false;
        }

        if (! $this->compatible($representative->budget_id, $activity->budget_id)) {
            return false;
        }

        if (! $this->compatible($representative->ticket_number, $activity->ticket_number)) {
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
     * The activity whose fields best represent the group: the earliest
     * activity with both a ticket and a budget, else the earliest ticketed
     * activity, else the earliest activity overall.
     *
     * @param  Collection<int, Activity>  $group
     */
    private function representative(Collection $group): Activity
    {
        $sorted = $group->sortBy('started_at');

        return $sorted->first(fn (Activity $activity) => $activity->ticket_number !== null && $activity->budget_id !== null)
            ?? $sorted->first(fn (Activity $activity) => $activity->ticket_number !== null)
            ?? $sorted->firstOrFail();
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
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, PreviousSequenceBooking>  $previousSequenceBookings
     */
    private function suggestionFromActivities(Collection $activities, CarbonInterface $date, Collection $previousSequenceBookings): EntrySuggestion
    {
        $template = $this->representative($activities);

        $suggestion = new EntrySuggestion;
        $suggestion->user_id = $template->user_id;
        $suggestion->budget_id = $template->budget_id;
        $suggestion->ticket_id = $template->ticket_id;
        $suggestion->ticket_number = $template->ticket_number;
        $suggestion->ticket_type = $template->ticket_type;
        $suggestion->customer_id = $template->customer_id;
        $suggestion->is_internal = $template->is_internal;
        $suggestion->date = $date->toDateString();
        $suggestion->setRelation('activities', $activities->sortBy('started_at')->values());

        if ($suggestion->customer_id === null) {
            $this->bookLikePreviousEntryOfSequence($suggestion, $activities, $previousSequenceBookings);
        }

        return $suggestion;
    }

    /**
     * A suggestion without a customer of its own takes the customer and budget
     * from the last entry booked on an earlier occurrence of the same sequence,
     * e.g. the previous instance of a recurring meeting.
     *
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, PreviousSequenceBooking>  $previousSequenceBookings
     */
    private function bookLikePreviousEntryOfSequence(EntrySuggestion $suggestion, Collection $activities, Collection $previousSequenceBookings): void
    {
        $previousBooking = $activities
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
