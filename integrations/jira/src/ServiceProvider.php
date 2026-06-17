<?php

namespace Timatic\Jira;

use App\Integrations\IntegrationTypeRegistry;
use App\Integrations\TicketProviderRegistry;
use App\Models\Integration;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\Jira\Filament\Pages\ProjectMappingPage;
use Timatic\Jira\Filament\Pages\SettingsPage;

class ServiceProvider extends BaseServiceProvider
{
    public const SOURCE_ID = 'jira';

    public function register(): void
    {
        $this->callAfterResolving(IntegrationTypeRegistry::class, function (IntegrationTypeRegistry $types): void {
            $types->register('jira', [
                'jira.projects' => ProjectMappingPage::class,
                'jira.settings' => SettingsPage::class,
            ])->landingPage(function (Integration $integration): string {
                $config = $integration->config ?? [];

                $isConnected = filled($config['access_token'] ?? null) && filled($config['cloud_id'] ?? null);

                return $isConnected ? ProjectMappingPage::class : SettingsPage::class;
            });
        });
    }

    public function boot(TicketProviderRegistry $ticketProviders): void
    {
        $ticketProviders->register('jira', TicketProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../config/jira.php', 'jira');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'jira');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'jira');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
