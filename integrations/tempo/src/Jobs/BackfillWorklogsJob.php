<?php

declare(strict_types=1);

namespace Timatic\Tempo\Jobs;

use App\Models\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Timatic\Tempo\Connector;
use Timatic\Tempo\DataTransferObjects\TempoWorklog;
use Timatic\Tempo\Requests\GetWorklogsRequest;
use Timatic\Tempo\WorklogImporter;

/** Copies the Tempo worklogs of the days $from through $to to entries. */
class BackfillWorklogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly int $tempoId,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    /** Queues one job per month from $since through today and returns how many were queued. */
    public static function dispatchMonths(Integration $tempo, CarbonImmutable $since): int
    {
        $today = CarbonImmutable::today($since->timezone);
        $count = 0;

        for ($from = $since->startOfDay(); $from->lte($today); $from = $from->addMonthNoOverflow()->startOfMonth()) {
            self::dispatch($tempo->id, $from, $from->endOfMonth()->startOfDay()->min($today));
            $count++;
        }

        return $count;
    }

    public function handle(WorklogImporter $importer): void
    {
        $tempo = Integration::findOrFail($this->tempoId);

        /** @var Collection<int, TempoWorklog> $worklogs */
        $worklogs = (new GetWorklogsRequest(from: $this->from, to: $this->to))->paginate(new Connector($tempo->config['api_token']))->collect()->collect();
        $importer->import($tempo, $worklogs);

        $config = $tempo->refresh()->config ?? [];
        $tempo->update(['config' => array_merge($config, [
            'worklog_sync_from' => min($config['worklog_sync_from'] ?? PHP_INT_MAX, $this->from->getTimestampMs()),
        ])]);
    }
}
