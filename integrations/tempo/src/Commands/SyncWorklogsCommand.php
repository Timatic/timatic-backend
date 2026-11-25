<?php

namespace Timatic\Tempo\Commands;

use App\Models\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Timatic\Tempo\Exceptions\WorklogSyncNotStartedException;
use Timatic\Tempo\Jobs\SyncWorklogsJob;

class SyncWorklogsCommand extends Command
{
    protected $signature = 'tempo:sync-worklogs {--since= : Day (Y-m-d) to start from instead of the stored position}';

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

            try {
                SyncWorklogsJob::start($integration, $since);
            } catch (WorklogSyncNotStartedException $exception) {
                $this->info("{$label}: {$exception->getMessage()}");

                continue;
            }

            if ($since === null && ! isset($config['worklog_sync_updated_since'])) {
                $this->warn("{$label}: no sync position stored yet, starting from now. Pass --since=YYYY-MM-DD to import older worklogs.");
            }

            $this->info("{$label}: worklog sync queued.");
        }

        return self::SUCCESS;
    }
}
