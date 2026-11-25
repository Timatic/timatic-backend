<?php

namespace Timatic\Tempo\Commands;

use App\Models\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Timatic\Tempo\Jobs\BackfillWorklogsJob;
use Timatic\Tempo\Jobs\SyncWorklogsJob;
use Timatic\Tempo\WorklogImporter;

class SyncWorklogsCommand extends Command
{
    protected $signature = 'tempo:sync-worklogs {--since= : Day (Y-m-d) from which to also import all worklogs again, one queued job per month}';

    protected $description = 'Queue a sync of the Tempo worklogs changed since the last run to Timatic entries';

    public function handle(): int
    {
        $since = $this->option('since') === null ? null : CarbonImmutable::parse($this->option('since'), config('timatic.preferred_timezone'));

        foreach (Integration::query()->where('type', 'tempo')->get() as $integration) {
            $label = $integration->display_title;
            $config = $integration->config ?? [];

            if (! ($config['sync_to_timatic'] ?? false)) {
                $this->info("{$label}: sync to Timatic is disabled in the Tempo integration settings.");

                continue;
            }

            if (! WorklogImporter::isReady($integration)) {
                $this->info("{$label}: Tempo token or connected Jira instance missing, nothing to sync.");

                continue;
            }

            if ($since !== null) {
                $months = BackfillWorklogsJob::dispatchMonths($integration, $since);
                $this->info("{$label}: {$months} month(s) of worklogs queued for import.");
            } elseif (! isset($config['worklog_sync_updated_since'])) {
                $this->warn("{$label}: no sync position stored yet, starting from now. Pass --since=YYYY-MM-DD to import older worklogs.");
            }

            SyncWorklogsJob::dispatch($integration->id);
            $this->info("{$label}: worklog sync queued.");
        }

        return self::SUCCESS;
    }
}
