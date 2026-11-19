<?php

namespace Timatic\Tempo\Commands;

use App\Models\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Timatic\Jira\WorklogSync;
use Timatic\Tempo\Connector;
use Timatic\Tempo\TempoWorklogFeed;

class SyncWorklogsCommand extends Command
{
    protected $signature = 'tempo:sync-worklogs {--since= : Day (Y-m-d) to start from instead of the stored position}';

    protected $description = 'Copy the Tempo worklogs changed since the last run to Timatic entries';

    public function handle(WorklogSync $worklogSync): int
    {
        foreach (Integration::query()->where('type', 'tempo')->get() as $integration) {
            $label = $integration->display_title;
            $config = $integration->config ?? [];

            if (! ($config['sync_to_timatic'] ?? false)) {
                $this->info("{$label}: sync to Timatic is disabled in the Tempo integration settings.");

                continue;
            }

            $jira = Integration::find((int) ($config['jira_integration_id'] ?? 0));

            if (blank($config['api_token'] ?? null) || $jira === null || blank($jira->config['access_token'] ?? null) || blank($jira->config['cloud_id'] ?? null)) {
                $this->info("{$label}: Tempo token or connected Jira instance missing, nothing to sync.");

                continue;
            }

            $updatedSince = $config['worklog_sync_updated_since'] ?? null;
            $deletedSince = $config['worklog_sync_deleted_since'] ?? null;

            if ($this->option('since') !== null) {
                $updatedSince = $deletedSince = CarbonImmutable::parse($this->option('since'), config('timatic.preferred_timezone'))->getTimestampMs();
            }

            if ($updatedSince === null || $deletedSince === null) {
                $updatedSince = $deletedSince = now()->getTimestampMs();
                $this->warn("{$label}: no sync position stored yet, starting from now. Pass --since=YYYY-MM-DD to import older worklogs.");
            }

            $feed = new TempoWorklogFeed(new Connector($config['api_token']));
            $report = $worklogSync->run($jira, (int) $updatedSince, (int) $deletedSince, $feed);

            $integration->update(['config' => array_merge($config, [
                'worklog_sync_updated_since' => $report->nextUpdatedSinceMilliseconds,
                'worklog_sync_deleted_since' => $report->nextDeletedSinceMilliseconds,
            ])]);

            $this->info("{$label}: created {$report->created}, updated {$report->updated}, deleted {$report->deleted}, skipped {$report->skipped} entries.");
        }

        return self::SUCCESS;
    }
}
