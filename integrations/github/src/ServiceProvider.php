<?php

namespace Timatic\GitHub;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public const SOURCE_ID = 'github';

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/github.php', 'github');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
