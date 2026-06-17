<?php

namespace Timatic\Nmbrs\Commands;

use App\Models\Integration;
use Illuminate\Console\Command;
use Throwable;
use Timatic\Nmbrs\Actions\PushOvertimesAction;

class PushOvertimesToNmbrsCommand extends Command
{
    protected $signature = 'nmbrs:push-overtimes';

    protected $description = 'Push approved overtimes to NMBRS as variable hour components';

    public function handle(PushOvertimesAction $action): int
    {
        $integration = Integration::where('type', 'nmbrs')->firstOrFail();
        $config = $integration->config ?? [];

        if (! ($config['sync_overtime_enabled'] ?? false)) {
            $this->info('Overtime sync is disabled.');

            return self::SUCCESS;
        }

        try {
            $result = $action->execute();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        $this->info("Done. {$result->exportedCount} overtime record(s) exported.");

        return self::SUCCESS;
    }
}
