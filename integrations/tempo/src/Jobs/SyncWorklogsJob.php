<?php

declare(strict_types=1);

namespace Timatic\Tempo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;
use Timatic\Tempo\DataTransferObjects\WorklogSyncRun;
use Timatic\Tempo\WorklogSync;
use Timatic\Tempo\WorklogSyncTracker;

/** Syncs one page of Tempo worklogs and queues the next page, so a long backfill never outlives the worker timeout. */
class SyncWorklogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public readonly WorklogSyncRun $run) {}

    public function handle(WorklogSync $worklogSync, WorklogSyncTracker $tracker): void
    {
        $active = $tracker->active($this->run->integrationId);

        // The claim of this run expired while it waited in the queue and another run took its place
        if ($active?->id !== $this->run->id && ! $tracker->claim($this->run)) {
            return;
        }

        $nextPage = $worklogSync->syncPage($this->run);

        if ($nextPage !== null) {
            self::dispatch($nextPage);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(WorklogSyncTracker::class)->close($this->run);
    }
}
