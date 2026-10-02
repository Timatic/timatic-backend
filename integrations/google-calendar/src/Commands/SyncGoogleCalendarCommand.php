<?php

namespace Timatic\GoogleCalendar\Commands;

use Illuminate\Console\Command;
use Timatic\GoogleCalendar\Jobs\SyncUserCalendarJob;
use Timatic\GoogleCalendar\Models\GoogleCalendarConnection;

class SyncGoogleCalendarCommand extends Command
{
    protected $signature = 'google-calendar:sync';

    protected $description = 'Sync Google Calendar events for all connected users';

    public function handle(): void
    {
        $connections = GoogleCalendarConnection::all();

        $this->info("Dispatching sync for {$connections->count()} connected user(s).");

        $connections->each(fn (GoogleCalendarConnection $connection) => SyncUserCalendarJob::dispatch($connection));
    }
}
