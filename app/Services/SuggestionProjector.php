<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\EntrySuggestion;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SuggestionProjector
{
    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, EntrySuggestion>  $dismissedSuggestions
     * @return Collection<int, EntrySuggestion>
     */
    public function project(Collection $activities, Collection $dismissedSuggestions, CarbonInterface $date): Collection
    {
        return $activities
            ->groupBy(fn (Activity $activity) => $this->groupKey($activity))
            ->reject(function (Collection $group) use ($dismissedSuggestions) {
                $first = $group->first();

                return $first !== null && $this->isDismissed($first, $dismissedSuggestions);
            })
            ->map(fn (Collection $group) => $this->suggestionFromActivities($group, $date))
            ->values();
    }

    private function groupKey(Activity $activity): string
    {
        return implode('|', [
            $activity->customer_id ?? '',
            (string) $activity->budget_id,
            $activity->ticket_number ?? '',
            match ($activity->is_internal) {
                null => '',
                true => '1',
                false => '0',
            },
        ]);
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
        /** @var Activity $template */
        $template = $activities->sortBy('started_at')->first();

        $suggestion = new EntrySuggestion;
        $suggestion->user_id = $template->user_id;
        $suggestion->budget_id = $template->budget_id;
        $suggestion->ticket_id = $template->ticket_id;
        $suggestion->ticket_number = $template->ticket_number;
        $suggestion->ticket_type = $template->ticket_type;
        $suggestion->customer_id = $template->customer_id;
        $suggestion->is_internal = $template->is_internal;
        $suggestion->date = $date->toDateString();
        $suggestion->setRelation('activities', $activities->values());

        return $suggestion;
    }
}
