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
        $groups = collect();

        $activities
            ->filter(fn (Activity $a) => $a->customer_id === null)
            ->each(fn (Activity $a) => $groups->push(collect([$a])));

        $activities
            ->filter(fn (Activity $a) => $a->customer_id !== null)
            ->groupBy('customer_id')
            ->each(function (Collection $customerActivities) use ($groups) {
                $groups->push(...$this->chainSequentially($customerActivities));
            });

        return $groups;
    }

    /**
     * Walks the customer's activities in chronological order, attaching each
     * one to the preceding group when it matches that group's representative.
     *
     * @param  Collection<int, Activity>  $activities
     * @return array<int, Collection<int, Activity>>
     */
    private function chainSequentially(Collection $activities): array
    {
        $sorted = $activities->sortBy('started_at')->values();

        $groups = [];
        $currentGroup = null;

        foreach ($sorted as $activity) {
            if ($currentGroup !== null && $this->chains($currentGroup, $activity)) {
                $currentGroup->push($activity);
            } else {
                $currentGroup = collect([$activity]);
                $groups[] = $currentGroup;
            }
        }

        return $groups;
    }

    /**
     * @param  Collection<int, Activity>  $group
     */
    private function chains(Collection $group, Activity $activity): bool
    {
        $representative = $this->representative($group);

        if ($representative->budget_id !== $activity->budget_id || $representative->is_internal !== $activity->is_internal) {
            return false;
        }

        if ($representative->ticket_number !== null && $activity->ticket_number !== null) {
            return $representative->ticket_number === $activity->ticket_number;
        }

        $last = $group->last() ?? $representative;

        return $last->ended_at->copy()->addMinutes(self::CHAIN_GAP_MINUTES)->isAfter($activity->started_at);
    }

    /**
     * The activity whose fields best represent the group: the earliest
     * ticketed activity if any, otherwise the earliest activity overall.
     *
     * @param  Collection<int, Activity>  $group
     */
    private function representative(Collection $group): Activity
    {
        $sorted = $group->sortBy('started_at');

        return $sorted->first(fn (Activity $a) => $a->ticket_number !== null) ?? $sorted->firstOrFail();
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
