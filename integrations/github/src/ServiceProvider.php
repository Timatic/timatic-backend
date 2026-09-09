<?php

namespace Timatic\GitHub;

use App\Integrations\IntegrationTypeRegistry;
use App\Integrations\TicketProviderRegistry;
use App\Models\Integration;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\GitHub\Filament\Pages\RepositoryMappingPage;
use Timatic\GitHub\Filament\Pages\SettingsPage;

class ServiceProvider extends BaseServiceProvider
{
    public const SOURCE_ID = 'github';

    public function register(): void
    {
        $this->callAfterResolving(IntegrationTypeRegistry::class, function (IntegrationTypeRegistry $types): void {
            $types->register('github', [
                'github.repositories' => RepositoryMappingPage::class,
                'github.settings' => SettingsPage::class,
            ])->landingPage(function (Integration $integration): string {
                $installations = app(InstallationService::class)->stored($integration->config ?? []);

                return $installations === [] ? SettingsPage::class : RepositoryMappingPage::class;
            });
        });
    }

    public function boot(TicketProviderRegistry $ticketProviders): void
    {
        $ticketProviders->register('github', TicketProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../config/github.php', 'github');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'github');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'github');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
