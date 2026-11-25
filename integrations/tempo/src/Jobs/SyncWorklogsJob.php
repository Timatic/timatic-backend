<?php

declare(strict_types=1);

namespace Timatic\Tempo\Jobs;

use App\Models\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection;
use Timatic\Tempo\Connector;
use Timatic\Tempo\DataTransferObjects\TempoDeletedWorklog;
use Timatic\Tempo\DataTransferObjects\TempoWorklog;
use Timatic\Tempo\Requests\GetDeletedWorklogsRequest;
use Timatic\Tempo\Requests\GetWorklogsRequest;
use Timatic\Tempo\WorklogImporter;

/** Copies the worklogs changed and deleted in Tempo since the stored position to entries, and moves the position on. */
class SyncWorklogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly int $tempoId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        // A second sync of the same changes adds nothing, so it is dropped instead of waiting
        return [(new WithoutOverlapping("tempo-sync-{$this->tempoId}"))->dontRelease()->expireAfter(600)];
    }

    public function handle(WorklogImporter $importer): void
    {
        $tempo = Integration::findOrFail($this->tempoId);
        $startedAt = now()->getTimestampMs();
        $updatedSince = (int) ($tempo->config['worklog_sync_updated_since'] ?? $startedAt);
        $deletedSince = (int) ($tempo->config['worklog_sync_deleted_since'] ?? $startedAt);
        $connector = new Connector($tempo->config['api_token']);

        /** @var Collection<int, TempoWorklog> $worklogs */
        $worklogs = (new GetWorklogsRequest(updatedFrom: CarbonImmutable::createFromTimestampMs($updatedSince)))->paginate($connector)->collect()->collect();
        $importer->import($tempo, $worklogs);

        /** @var Collection<int, TempoDeletedWorklog> $deleted */
        $deleted = (new GetDeletedWorklogsRequest(CarbonImmutable::createFromTimestampMs($deletedSince)))->paginate($connector)->collect()->collect();
        $importer->remove($deleted->pluck('id')->unique()->values());

        // Read again: the settings may have been saved while the sync was busy
        $config = $tempo->refresh()->config ?? [];
        $tempo->update(['config' => array_merge($config, [
            // Worklogs changed while the sync was busy may have been paged past, so never move beyond its start
            'worklog_sync_updated_since' => min($worklogs->max(fn (TempoWorklog $worklog) => $worklog->updatedAt->getTimestampMs()) ?? $updatedSince, $startedAt),
            'worklog_sync_deleted_since' => max($deletedSince, $deleted->max(fn (TempoDeletedWorklog $worklog) => $worklog->deletedAt->getTimestampMs()) ?? 0),
            'worklog_sync_from' => min($config['worklog_sync_from'] ?? PHP_INT_MAX, $updatedSince),
            'worklog_synced_until' => $startedAt,
        ])]);
    }
}
