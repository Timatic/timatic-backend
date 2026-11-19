<?php

namespace Timatic\Tempo\Listeners;

use App\Events\EntrySaved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Context;
use Timatic\Tempo\WorklogWriter;

class PushEntryToTempo implements ShouldQueue
{
    // ponytail: a retry after a successful create would add a second Tempo worklog, so fail once and let an admin retry the job
    public int $tries = 1;

    public function __construct(private readonly WorklogWriter $writer) {}

    public function handle(EntrySaved $event): void
    {
        if (Context::has('entry_sync_source')) {
            return;
        }

        $this->writer->push($event->getEntry());
    }
}
