<?php

namespace Timatic\Tempo\Commands;

use App\Models\Integration;
use Illuminate\Console\Command;
use Timatic\Tempo\Jobs\SyncWorklogsJob;
use Timatic\Tempo\WorklogImporter;

class SyncWorklogsCommand extends Command
{
    protected $signature = 'tempo:sync-worklogs';

    protected $description = 'Queue a sync of the Tempo worklogs changed since the last run to Timatic entries';

    public function handle(): int
    {
        foreach (Integration::query()->where('type', 'tempo')->get() as $integration) {
            if (! ($integration->config['sync_to_timatic'] ?? false)) {
                continue;
            }

            if (! WorklogImporter::isReady($integration)) {
                $this->info("{$integration->display_title}: Tempo token or connected Jira instance missing, nothing to sync.");

                continue;
            }

            SyncWorklogsJob::dispatch($integration->id);
        }

        return self::SUCCESS;
    }
}
