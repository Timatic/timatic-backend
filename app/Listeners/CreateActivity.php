<?php

namespace App\Listeners;

use App\Events\EventCreated;
use App\Models\Activity;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class CreateActivity implements ShouldQueue
{
    use SerializesModels;

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
     */
    public function handle(EventCreated $eventCreated): void
    {
        $event = $eventCreated->getEvent();

        $adjacentActivity = $this->getAdjacentActivity($event);
        if ($adjacentActivity && $this->canAbsorbEvent($adjacentActivity, $event)) {
            $startedAt = $adjacentActivity->started_at;
            if ($event->started_at) {
                $startedAt = $adjacentActivity->started_at->min($event->started_at);
            }

            $adjacentActivity->events()->save($event);
            $adjacentActivity->started_at = $startedAt;
            $adjacentActivity->ended_at = $event->ended_at->max($adjacentActivity->ended_at);
            $adjacentActivity->save();
        } else {
            $this->createActivityFromEvent($event);
        }
    }

    private function canAbsorbEvent(Activity $lastActivity, Event $event): bool
    {
        $suggestion = $lastActivity->entrySuggestion;

        if (is_null($event->customer_id)) {
            return false;
        }

        return $lastActivity->event_type_id === $event->event_type_id
            && $lastActivity->customer_id === $event->customer_id
            && ($lastActivity->ticket_number === $event->ticket_number && $event->ticket_number !== null)
            && ($suggestion === null || $suggestion->trashed() === false);
    }

    private function getAdjacentActivity(Event $event): ?Activity
    {
        /** @var Activity|null $adjacentActivity */
        $adjacentActivity = Activity::query()
            ->where('ended_at', '>', $this->getEstimatedStartedAt($event)->subMinutes(15))
            ->where('ended_at', '<', $event->ended_at)
            ->where('user_id', '=', $event->user_id)
            ->where('source_id', '=', $event->source_id)
            ->first();

        return $adjacentActivity;
    }

    private function getEstimatedStartedAt(Event $event): Carbon
    {
        return $event->started_at ?: $event->ended_at->subMinutes(15);
    }

    private function createActivityFromEvent(Event $event): ?Activity
    {
        $activity = new Activity;
        $activity->source_id = $event->source_id;
        $activity->user_id = $event->user_id;
        $activity->budget_id = $event->budget_id;
        $activity->ticket_id = $event->ticket_id;
        $activity->ticket_number = $event->ticket_number;
        $activity->ticket_type = $event->ticket_type;
        $activity->title = $event->title;
        $activity->description = $event->description;
        $activity->customer_id = $event->customer_id;
        $activity->started_at = $this->getEstimatedStartedAt($event);
        $activity->ended_at = $event->ended_at;
        $activity->is_internal = $event->is_internal;
        $activity->event_type_id = $event->eventType->id ?? null;

        $trimmedActivities = collect();
        $absorbedActivities = collect();
        if (config('timatic.feature.activity_overlap_detection')) {
            $overlappingActivities = $this->getOverlappingActivities($activity);
            $isDominant = function (Activity $overlappingActivity) use ($activity) {
                return ! is_null($overlappingActivity->eventType)
                    && $overlappingActivity->eventType->weight >= (int) $activity->eventType?->weight;
            };

            $dominantActivities = $overlappingActivities->filter($isDominant);
            $activity->started_at = $this->startedAtAfterDominantActivities($activity, $dominantActivities);

            if (! $activity->ended_at->isAfter($activity->started_at)) {
                $this->attachEventToCoveringActivity($event, $dominantActivities);

                return null;
            }

            $trimmedActivities = $this->trimSubordinateActivities($activity, $overlappingActivities->reject($isDominant));
            $isCollapsed = function (Activity $trimmedActivity) {
                return ! $trimmedActivity->ended_at->isAfter($trimmedActivity->started_at);
            };
            $absorbedActivities = $trimmedActivities->filter($isCollapsed);
            $trimmedActivities = $trimmedActivities->reject($isCollapsed);
        }

        $this->db->transaction(function () use ($activity, $event, $trimmedActivities, $absorbedActivities) {
            $activity->save();
            $activity->events()->save($event);

            $trimmedActivities->each(fn (Activity $trimmedActivity) => $trimmedActivity->save());

            $absorbedActivities->each(function (Activity $absorbedActivity) use ($activity) {
                $absorbedActivity->events()->update(['activity_id' => $activity->id]);
                $absorbedActivity->delete();
            });
        });

        return $activity;
    }

    /**
     * @return Collection<int, Activity>
     */
    private function getOverlappingActivities(Activity $activity): Collection
    {
        return Activity::query()
            ->with('eventType')
            ->where('user_id', $activity->user_id)
            ->where(function (Builder $query) use ($activity) {
                $query
                    ->where(function (Builder $query) use ($activity) {
                        $query
                            ->where('started_at', '<', $activity->started_at)
                            ->where('ended_at', '>', $activity->started_at);
                    })
                    ->orWhere(function (Builder $query) use ($activity) {
                        $query
                            ->where('started_at', '<', $activity->ended_at)
                            ->where('ended_at', '>', $activity->ended_at);
                    })
                    ->orWhere(function (Builder $query) use ($activity) {
                        $query
                            ->where('started_at', '>=', $activity->started_at)
                            ->where('ended_at', '<=', $activity->ended_at);
                    });
            })
            ->orderBy('started_at')
            ->get();
    }

    /**
     * Dominant activities keep their period, so the new activity starts after
     * the last of them. A start beyond the activity's end means the event was
     * fully covered by dominant activities.
     *
     * @param  Collection<int, Activity>  $dominantActivities
     */
    private function startedAtAfterDominantActivities(Activity $activity, Collection $dominantActivities): Carbon
    {
        return $dominantActivities->reduce(
            fn (Carbon $startedAt, Activity $dominantActivity): Carbon => $startedAt->max($dominantActivity->ended_at),
            $activity->started_at,
        );
    }

    /**
     * @param  Collection<int, Activity>  $coveringCandidates
     */
    private function attachEventToCoveringActivity(Event $event, Collection $coveringCandidates): void
    {
        $coveringActivity = $coveringCandidates->first(function (Activity $coveringCandidate) use ($event) {
            return $coveringCandidate->started_at->lessThanOrEqualTo($this->getEstimatedStartedAt($event))
                && $coveringCandidate->ended_at->greaterThanOrEqualTo($event->ended_at)
                && $this->canAbsorbEvent($coveringCandidate, $event);
        });

        $coveringActivity?->events()->save($event);
    }

    /**
     * The new activity gets priority, so subordinate activities still overlapping
     * its final period lose the overlapping part. An activity whose trimmed period
     * collapses is absorbed: the new activity takes over its events.
     *
     * @param  Collection<int, Activity>  $subordinateActivities
     * @return Collection<int, Activity>
     */
    private function trimSubordinateActivities(Activity $activity, Collection $subordinateActivities): Collection
    {
        return $subordinateActivities
            ->filter(function (Activity $subordinateActivity) use ($activity) {
                return $subordinateActivity->started_at->lessThan($activity->ended_at)
                    && $subordinateActivity->ended_at->greaterThan($activity->started_at);
            })
            ->each(function (Activity $subordinateActivity) use ($activity) {
                if ($subordinateActivity->ended_at < $activity->ended_at) {
                    $subordinateActivity->ended_at = $activity->started_at;
                } else {
                    $subordinateActivity->started_at = $activity->ended_at;
                }
            });
    }
}
