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
        if ($adjacentActivity && $this->canBeMergedWithAdjacentActivity($adjacentActivity, $event)) {
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

    private function canBeMergedWithAdjacentActivity(Activity $lastActivity, Event $event): bool
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

        $absorbedActivities = collect();
        if (config('timatic.feature.activity_overlap_detection')) {
            $absorbedActivities = $this->trimOverlappingActivities($activity);

            if (! $activity->ended_at->isAfter($activity->started_at)) {
                $this->attachEventToCoveringActivity($event);

                return null;
            }
        }

        $this->db->transaction(function () use ($activity, $event, $absorbedActivities) {
            $activity->save();
            $activity->events()->save($event);

            $absorbedActivities->each(function (Activity $absorbedActivity) use ($activity) {
                $absorbedActivity->events()->update(['activity_id' => $activity->id]);
                $absorbedActivity->delete();
            });
        });

        return $activity;
    }

    private function attachEventToCoveringActivity(Event $event): void
    {
        /** @var ?Activity $coveringActivity */
        $coveringActivity = Activity::query()
            ->where('user_id', $event->user_id)
            ->where('started_at', '<=', $event->ended_at)
            ->where('ended_at', '>=', $event->ended_at)
            ->first();

        $coveringActivity?->events()->save($event);
    }

    /**
     * Trims activities overlapping the new activity's period. An existing activity whose
     * trimmed period would collapse is returned for absorption: the new activity takes
     * over its events and the empty activity is deleted.
     *
     * @return Collection<int, Activity>
     */
    private function trimOverlappingActivities(Activity $activity): Collection
    {
        /** @var Collection|Activity[] $overlappingActivities */
        $overlappingActivities = Activity::query()
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
            ->get();

        $absorbedActivities = collect();

        $overlappingActivities->each(function ($overlappingActivity) use ($activity, $absorbedActivities) {
            /** @var Activity $overlappingActivity */
            if (! is_null($overlappingActivity->eventType)
                && $overlappingActivity->eventType->weight >= (int) $activity->eventType?->weight) {
                // overlappingActivity gets priority, move startedAt to after this activity
                $activity->started_at = $overlappingActivity->ended_at;
            } else {
                // new event gets priority, reduce overlapping start or end time from overlappingActivity
                if ($overlappingActivity->ended_at < $activity->ended_at) {
                    $overlappingActivity->ended_at = $activity->started_at;
                } else {
                    $overlappingActivity->started_at = $activity->ended_at;
                }

                if ($overlappingActivity->ended_at->isAfter($overlappingActivity->started_at)) {
                    $overlappingActivity->save();
                } else {
                    $absorbedActivities->push($overlappingActivity);
                }
            }
        });

        return $absorbedActivities;
    }
}
