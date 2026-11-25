<?php

declare(strict_types=1);

namespace Timatic\Tempo\Jobs;

use App\Models\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Timatic\Tempo\Connector;
use Timatic\Tempo\DataTransferObjects\TempoWorklog;
use Timatic\Tempo\Requests\GetWorklogsRequest;
use Timatic\Tempo\WorklogImporter;

/** Copies the Tempo worklogs of the days $from through $to to entries. */
class BackfillWorklogsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly int $tempoId,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    /** Queues one job per month of the days $from through $until as a batch, whose id is stored to show its progress, and returns how many were queued. */
    public static function dispatchMonths(Integration $tempo, CarbonImmutable $from, CarbonImmutable $until): int
    {
        $jobs = [];

        for ($start = $from; $start->lte($until); $start = $start->addMonthNoOverflow()->startOfMonth()) {
            $jobs[] = new self($tempo->id, $start, $start->endOfMonth()->startOfDay()->min($until));
        }

        // A failed month must not cancel the months after it
        $batch = Bus::batch($jobs)->name("Tempo worklog import {$tempo->id}")->allowFailures()->dispatch();
        $tempo->update(['config' => array_merge($tempo->refresh()->config ?? [], ['worklog_backfill_batch_id' => $batch->id])]);

        return count($jobs);
    }

    public function handle(WorklogImporter $importer): void
    {
        $tempo = Integration::findOrFail($this->tempoId);

        /** @var Collection<int, TempoWorklog> $worklogs */
        $worklogs = GetWorklogsRequest::between($this->from, $this->to)->paginate(new Connector($tempo->config['api_token']))->collect()->collect();
        $importer->import($tempo, $worklogs);
    }
}
