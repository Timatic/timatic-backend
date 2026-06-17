<?php

namespace Timatic\Bitbucket;

use App\Integrations\IntegrationTypeRegistry;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\Bitbucket\Filament\Pages\RepositoryMappingPage;
use Timatic\Bitbucket\Filament\Pages\SettingsPage;

class ServiceProvider extends BaseServiceProvider
{
    public const SOURCE_ID = 'bitbucket';

    public function register(): void
    {
        $this->callAfterResolving(IntegrationTypeRegistry::class, function (IntegrationTypeRegistry $types): void {
            $types->register('bitbucket', [
                'bitbucket.repositories' => RepositoryMappingPage::class,
                'bitbucket.settings' => SettingsPage::class,
            ]);
        });
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bitbucket.php', 'bitbucket');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bitbucket');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'bitbucket');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
