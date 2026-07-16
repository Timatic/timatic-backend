<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\Entry;
use App\Models\EntrySuggestion;
use App\Models\Event;
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

        $events = Event::query()
            ->with('eventType')
            ->where('user_id', $this->userId)
            ->where('ended_at', '>=', $day)
            ->where('ended_at', '<', $day->copy()->addDay())
            ->get();

        $entries = Entry::query()
            ->where('user_id', $this->userId)
            ->where('started_at', '<', $day->copy()->addDay())
            ->where('ended_at', '>', $day)
            ->get();

        $dismissedSuggestions = EntrySuggestion::onlyTrashed()
            ->whereDoesntHave('entry')
            ->where('user_id', $this->userId)
            ->where('date', $day->toDateString())
            ->get();

        $activities = $activityProjector->project($events, $entries);
        $suggestions = $suggestionProjector->project($activities, $dismissedSuggestions, $day);

        $db->transaction(function () use ($activities, $suggestions, $day) {
            $this->deleteProjectedState($day);
            $this->saveProjectedState($activities, $suggestions);
        });
    }

    private function deleteProjectedState(CarbonInterface $day): void
    {
        EntrySuggestion::query()
            ->where('user_id', $this->userId)
            ->where('date', $day->toDateString())
            ->whereDoesntHave('entry')
            ->whereNull('deleted_at')
            ->forceDelete();

        Activity::query()
            ->where('user_id', $this->userId)
            ->where('ended_at', '>=', $day)
            ->where('ended_at', '<', $day->copy()->addDay())
            ->delete();
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, EntrySuggestion>  $suggestions
     */
    private function saveProjectedState(Collection $activities, Collection $suggestions): void
    {
        $activities->each(function (Activity $activity) {
            $activity->save();
            $activity->events()->saveMany($activity->events);
        });

        $suggestions->each(function (EntrySuggestion $suggestion) {
            $suggestion->save();
            Activity::whereKey($suggestion->activities->pluck('id'))
                ->update(['entry_suggestion_id' => $suggestion->id]);
        });
    }
}
