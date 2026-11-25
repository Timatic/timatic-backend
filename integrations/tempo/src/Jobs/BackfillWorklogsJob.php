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

    public function handle(WorklogImporter $importer): void
    {
        $tempo = Integration::findOrFail($this->tempoId);
        $connector = new Connector($tempo->config['api_token']);

        /** @var Collection<int, TempoWorklog> $worklogs */
        $worklogs = GetWorklogsRequest::between($this->from, $this->to)
            ->paginate($connector)
            ->collect()
            ->collect();
        $importer->import($tempo, $worklogs);
    }
}
