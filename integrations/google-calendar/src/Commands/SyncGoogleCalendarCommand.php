<?php

namespace Timatic\GoogleCalendar\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Timatic\GoogleCalendar\Jobs\SyncUserCalendarJob;

class SyncGoogleCalendarCommand extends Command
{
    protected $signature = 'google-calendar:sync';

    protected $description = 'Sync Google Calendar events for all connected users';

    public function handle(): void
    {
        $users = User::whereNotNull('oauth_refresh_token')->get();

        $this->info("Dispatching sync for {$users->count()} connected user(s).");

        $users->each(fn (User $user) => SyncUserCalendarJob::dispatch($user));
    }
}
