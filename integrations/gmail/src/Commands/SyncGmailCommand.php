<?php

declare(strict_types=1);

namespace Timatic\Gmail\Commands;

use Illuminate\Console\Command;
use Timatic\Gmail\Jobs\SyncUserMailJob;
use Timatic\Gmail\Models\GmailConnection;

class SyncGmailCommand extends Command
{
    protected $signature = 'gmail:sync';

    protected $description = 'Sync sent mail and saved drafts from Gmail for all connected users';

    public function handle(): void
    {
        $connections = GmailConnection::all();

        $this->info("Dispatching sync for {$connections->count()} connected user(s).");

        $connections->each(fn (GmailConnection $connection) => SyncUserMailJob::dispatch($connection));
    }
}
