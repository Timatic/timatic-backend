<?php

namespace Timatic\Tempo\Listeners;

use App\Events\EntryDeleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Context;
use Timatic\Tempo\WorklogWriter;

class RemoveEntryFromTempo implements ShouldQueue
{
    public int $tries = 1;

    public function __construct(private readonly WorklogWriter $writer) {}

    public function handle(EntryDeleted $event): void
    {
        if (Context::has('entry_sync_source')) {
            return;
        }

        $this->writer->remove($event->getEntry());
    }
}
