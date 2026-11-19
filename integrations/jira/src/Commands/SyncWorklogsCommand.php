<?php

namespace Timatic\Jira\Commands;

use App\Models\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Timatic\Jira\WorklogSourceRegistry;
use Timatic\Jira\WorklogSync;

class SyncWorklogsCommand extends Command
{
    protected $signature = 'jira:sync-worklogs {--since= : Day (Y-m-d) to start from instead of the stored position}';

    protected $description = 'Copy the Jira worklogs changed since the last run to Timatic entries';

    public function handle(WorklogSync $worklogSync, WorklogSourceRegistry $sources): int
    {
        foreach (Integration::query()->where('type', 'jira')->get() as $integration) {
            $label = $integration->display_title;

            if (blank($integration->config['access_token'] ?? null) || blank($integration->config['cloud_id'] ?? null)) {
                $this->info("{$label}: Jira is not connected, nothing to sync.");

                continue;
            }

            if ($sources->isClaimed($integration)) {
                $this->info("{$label}: worklogs are synced by another integration.");

                continue;
            }

            if (! ($integration->config['worklog_sync_enabled'] ?? false)) {
                $this->info("{$label}: worklog sync is disabled in the Jira integration settings.");

                continue;
            }

            $updatedSince = $integration->config['worklog_sync_updated_since'] ?? null;
            $deletedSince = $integration->config['worklog_sync_deleted_since'] ?? null;

            if ($this->option('since') !== null) {
                $updatedSince = $deletedSince = CarbonImmutable::parse($this->option('since'), config('timatic.preferred_timezone'))->getTimestampMs();
            }

            if ($updatedSince === null || $deletedSince === null) {
                $updatedSince = $deletedSince = now()->getTimestampMs();
                $this->warn("{$label}: no sync position stored yet, starting from now. Pass --since=YYYY-MM-DD to import older worklogs.");
            }

            $report = $worklogSync->run($integration, (int) $updatedSince, (int) $deletedSince);

            // refreshIfExpired may have rewritten the config during the run
            $integration->refresh();
            $integration->update(['config' => array_merge($integration->config, [
                'worklog_sync_updated_since' => $report->nextUpdatedSinceMilliseconds,
                'worklog_sync_deleted_since' => $report->nextDeletedSinceMilliseconds,
            ])]);

            $this->info("{$label}: created {$report->created}, updated {$report->updated}, deleted {$report->deleted}, skipped {$report->skipped} entries.");
        }

        return self::SUCCESS;
    }
}
