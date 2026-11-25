<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('suggestions:remind')
    ->weekly()->mondays()->at('10:00')
    ->onOneServer();

Schedule::command('budget:balance-notification')
    ->monthly()->at('08:00')
    ->onOneServer();

Schedule::command('model:prune')
    ->daily()->at('03:00')
    ->onOneServer();

// after the GitHub repository scan at 07:30
Schedule::command('repositories:unmapped-digest')
    ->dailyAt('08:00')
    ->onOneServer();
