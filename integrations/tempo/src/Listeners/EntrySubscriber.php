<?php

namespace Timatic\Tempo\Listeners;

use App\Events\EntryDeleted;
use App\Events\EntrySaved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Context;
use Timatic\Tempo\WorklogWriter;

// @arch-ignore app-boundary: reacts to entry saves and deletes, the only way to mirror them to Tempo
class EntrySubscriber implements ShouldQueue
{
    // ponytail: a retry after a successful create would add a second Tempo worklog, so fail once and let an admin retry the job
    public int $tries = 1;

    public function __construct(private readonly WorklogWriter $writer) {}

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            EntrySaved::class => 'push',
            EntryDeleted::class => 'remove',
        ];
    }

    public function push(EntrySaved $event): void
    {
        if (! Context::has('entry_sync_source')) {
            $this->writer->push($event->getEntry());
        }
    }

    public function remove(EntryDeleted $event): void
    {
        if (! Context::has('entry_sync_source')) {
            $this->writer->remove($event->getEntry());
        }
    }
}
