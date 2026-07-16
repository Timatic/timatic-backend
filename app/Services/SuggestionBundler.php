<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\EntrySuggestion;
use Illuminate\Database\Eloquent\Builder;

class SuggestionBundler
{
    public function bundle(Activity $activity): EntrySuggestion
    {
        $suggestion = $this->findMatchingSuggestion($activity)
            ?? $this->newSuggestionFromActivity($activity);

        return $this->attach($suggestion, $activity);
    }

    public function createNewSuggestionFor(Activity $activity): EntrySuggestion
    {
        return $this->attach($this->newSuggestionFromActivity($activity), $activity);
    }

    private function attach(EntrySuggestion $suggestion, Activity $activity): EntrySuggestion
    {
        $suggestion->save();
        $suggestion->activities()->save($activity);

        return $suggestion;
    }

    private function findMatchingSuggestion(Activity $activity): ?EntrySuggestion
    {
        $query = EntrySuggestion::query()
            ->whereDoesntHave('entry')
            ->where('user_id', $activity->user_id)
            ->where('date', $this->suggestionDateFor($activity));

        $this->whereNullable($query, 'customer_id', $activity->customer_id);
        $this->whereNullable($query, 'budget_id', $activity->budget_id);
        $this->whereNullable($query, 'ticket_number', $activity->ticket_number);
        $this->whereNullable($query, 'is_internal', $activity->is_internal);

        /** @var ?EntrySuggestion */
        return $query->first();
    }

    private function newSuggestionFromActivity(Activity $activity): EntrySuggestion
    {
        $suggestion = new EntrySuggestion;
        $suggestion->user_id = $activity->user_id;
        $suggestion->budget_id = $activity->budget_id;
        $suggestion->ticket_id = $activity->ticket_id;
        $suggestion->ticket_number = $activity->ticket_number;
        $suggestion->ticket_type = $activity->ticket_type;
        $suggestion->customer_id = $activity->customer_id;
        $suggestion->is_internal = $activity->is_internal;
        $suggestion->date = $activity->started_at->setTimezone(config('timatic.preferred_timezone'));

        return $suggestion;
    }

    private function suggestionDateFor(Activity $activity): string
    {
        return $activity->started_at
            ->setTimezone(config('timatic.preferred_timezone'))
            ->toDateString();
    }

    /**
     * @param  Builder<EntrySuggestion>  $query
     */
    private function whereNullable(Builder $query, string $column, mixed $value): void
    {
        if ($value === null) {
            $query->whereNull($column);
        } else {
            $query->where($column, $value);
        }
    }
}
