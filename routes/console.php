<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('suggestions:remind')
    ->weekly()->mondays()->at('10:00')
    ->onOneServer();

Schedule::command('budget:balance-notification')
    ->monthly()->at('08:00')
    ->onOneServer();
