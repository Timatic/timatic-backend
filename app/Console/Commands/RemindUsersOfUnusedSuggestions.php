<?php

namespace App\Console\Commands;

use App\Jobs;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;

class RemindUsersOfUnusedSuggestions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suggestions:remind';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders to users that have unused suggestions in last week.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        Jobs\RemindUsersOfUnusedSuggestions::dispatch();

        return BaseCommand::SUCCESS;
    }
}
