<?php

namespace App\Jobs;

use App\DataTransferObjects\TimeSlot;
use App\Models\Activity;
use App\Models\Entry;
use App\Models\EntrySuggestion;
use App\Queries\UserDayEvents;
use App\Queries\UserDismissedSuggestionsOnDate;
use App\Queries\UserEntriesInDay;
use App\Services\ActivityProjector;
use App\Services\SuggestionProjector;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class RebuildUserDay implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $userId,
        public readonly string $date,
    ) {}

    public function uniqueId(): string
    {
        return $this->userId.':'.$this->date;
    }

    public function handle(ActivityProjector $activityProjector, SuggestionProjector $suggestionProjector, DatabaseManager $db): void
    {
        $day = Carbon::parse($this->date, config('timatic.preferred_timezone'))->startOfDay();

        $events = UserDayEvents::query($this->userId, $day)->get();
        $entryPeriods = UserEntriesInDay::query($this->userId, $day)->get()
            ->map(fn (Entry $entry) => new TimeSlot($entry->started_at, $entry->ended_at));
        $dismissedSuggestions = UserDismissedSuggestionsOnDate::query($this->userId, $day)->get();

        $activities = $activityProjector->project($events, $entryPeriods);
        $suggestions = $suggestionProjector->project($activities, $dismissedSuggestions, $day);

        $db->transaction(function () use ($activities, $suggestions, $day) {
            $this->deleteProjectedState($day);
            $this->saveProjectedState($activities, $suggestions);
        });
    }

    private function deleteProjectedState(CarbonInterface $day): void
    {
        Activity::query()
            ->where('user_id', $this->userId)
            ->where('ended_at', '>=', $day)
            ->where('ended_at', '<', $day->copy()->addDay())
            ->delete();

        EntrySuggestion::query()
            ->where('user_id', $this->userId)
            ->where('date', $day->toDateString())
            ->whereDoesntHave('entry')
            ->whereNull('deleted_at')
            ->forceDelete();
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, EntrySuggestion>  $suggestions
     */
    private function saveProjectedState(Collection $activities, Collection $suggestions): void
    {
        $suggestions->each(function (EntrySuggestion $suggestion) {
            $suggestion->save();
            $suggestion->activities->each(fn (Activity $activity) => $activity->entry_suggestion_id = $suggestion->id);
        });

        $activities->each(function (Activity $activity) {
            $activity->save();
            $activity->events()->saveMany($activity->events);
        });
    }
}
