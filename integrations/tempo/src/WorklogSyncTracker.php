<?php

declare(strict_types=1);

namespace Timatic\Tempo;

use Illuminate\Support\Facades\Cache;
use Timatic\Tempo\DataTransferObjects\WorklogSyncRun;

/** Holds the active worklog sync per integration, as the lock against a second run over the same pages. */
final class WorklogSyncTracker
{
    // A run whose worker died stops blocking the next run after this long without progress
    private const ACTIVE_SECONDS = 600;

    public function active(int $integrationId): ?WorklogSyncRun
    {
        return Cache::get("tempo.worklog_sync.active.{$integrationId}");
    }

    public function claim(WorklogSyncRun $run): bool
    {
        return Cache::add("tempo.worklog_sync.active.{$run->integrationId}", $run, self::ACTIVE_SECONDS);
    }

    public function update(WorklogSyncRun $run): void
    {
        Cache::put("tempo.worklog_sync.active.{$run->integrationId}", $run, self::ACTIVE_SECONDS);
    }

    public function close(WorklogSyncRun $run): void
    {
        // A run that lost its claim must not release the run that took over
        if ($this->active($run->integrationId)?->id === $run->id) {
            Cache::forget("tempo.worklog_sync.active.{$run->integrationId}");
        }
    }
}
