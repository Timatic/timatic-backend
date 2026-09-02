<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\EntrySuggestion;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SuggestionProjector
{
    private const CHAIN_GAP_MINUTES = 15;

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, EntrySuggestion>  $dismissedSuggestions
     * @return Collection<int, EntrySuggestion>
     */
    public function project(Collection $activities, Collection $dismissedSuggestions, CarbonInterface $date): Collection
    {
        return $this->buildGroups($activities)
            ->reject(fn (Collection $group) => $this->isDismissed($this->representative($group), $dismissedSuggestions))
            ->map(fn (Collection $group) => $this->suggestionFromActivities($group, $date))
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
            if ($currentGroup !== null && $this->chainsOnto($activity, $currentGroup)) {
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
    private function chainsOnto(Activity $activity, Collection $group): bool
    {
        $representative = $this->representative($group);

        if ($representative->is_internal !== $activity->is_internal) {
            return false;
        }

        if (! $this->compatible($representative->budget_id, $activity->budget_id)) {
            return false;
        }

        if ($representative->ticket_number !== null && $activity->ticket_number !== null) {
            return $representative->ticket_number === $activity->ticket_number;
        }

        $previous = $group->last() ?? $representative;

        return $previous->ended_at->copy()->addMinutes(self::CHAIN_GAP_MINUTES)->isAfter($activity->started_at);
    }

    /**
     * Two values are compatible for chaining when either is unset, or when
     * both are set and equal.
     */
    private function compatible(?int $a, ?int $b): bool
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
    private function isDismissed(Activity $activity, Collection $dismissedSuggestions): bool
    {
        return $dismissedSuggestions->contains(fn (EntrySuggestion $dismissed) => $dismissed->customer_id === $activity->customer_id
            && $dismissed->budget_id === $activity->budget_id
            && $dismissed->ticket_number === $activity->ticket_number
            && $dismissed->is_internal === $activity->is_internal);
    }

    /**
     * @param  Collection<int, Activity>  $activities
     */
    private function suggestionFromActivities(Collection $activities, CarbonInterface $date): EntrySuggestion
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

        return $suggestion;
    }
}
