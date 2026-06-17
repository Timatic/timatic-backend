<?php

namespace App\Listeners;

use App\Events\ActivityCreated;
use App\Models\Activity;
use App\Models\EntrySuggestion;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class CreateSuggestion implements ShouldQueue
{
    protected DatabaseManager $db;

    /**
     * Create the event listener.
     */
    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }

    /**
     * Handle the event.
     *
     *
     * @throws Exception|Throwable
     */
    public function handle(ActivityCreated $activityCreated): void
    {
        $activity = $activityCreated->getActivity();

        if (config('timatic.feature.build_stacked_suggestions')) {
            $suggestion = $this->findMergeableSuggestion($activity);
        } else {
            $suggestion = null;
        }

        if ($suggestion === null) {
            // not found? create a new suggestion
            $suggestion = $this->createSuggestionFromActivity($activity);
        }

        if ($suggestion->ticket_number === null && $activity->ticket_number !== null) {
            $suggestion->ticket_id = $activity->ticket_id;
            $suggestion->ticket_number = $activity->ticket_number;
        }

        $suggestion->save();
        $suggestion->activities()->save($activity);
    }

    private function findMergeableSuggestion(Activity $activity): ?EntrySuggestion
    {
        $suggestion = EntrySuggestion::query()
            ->where('user_id', '=', $activity->user_id)
            ->where('customer_id', '=', $activity->customer_id)
            // we only handle suggestions from the same day
            ->where('date', '=', $activity->started_at->setTimezone(config('timatic.preferred_timezone'))->toDateString())
            ->where(function (Builder $query) use ($activity) {
                $query
                    ->where('ticket_number', '=', $activity->ticket_number)
                    ->orWhereNull('ticket_number');
            })
            ->first();

        /** @var ?EntrySuggestion $suggestion */
        if ($suggestion && $suggestion->ticket_number === null) {
            return null;
        }

        return $suggestion;
    }

    private function createSuggestionFromActivity(Activity $activity): EntrySuggestion
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
}
