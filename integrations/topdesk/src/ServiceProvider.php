<?php

namespace Timatic\Topdesk;

use App\Integrations\IntegrationTypeRegistry;
use App\Integrations\TicketProviderRegistry;
use App\Models\Integration;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\Topdesk\Filament\Pages\SettingsPage;

class ServiceProvider extends BaseServiceProvider
{
    public const SOURCE_ID = 'topdesk';

    public function register(): void
    {
        $this->app->bind(ApiCredentials::class, function (): ApiCredentials {
            $integration = Integration::where('type', 'topdesk')->first();

            return new ApiCredentials(
                baseUrl: $integration?->config['base_url'] ?? '',
                username: $integration?->config['username'] ?? '',
                apiToken: $integration?->config['api_token'] ?? '',
            );
        });

        $this->callAfterResolving(IntegrationTypeRegistry::class, function (IntegrationTypeRegistry $types): void {
            $types->register('topdesk', [
                'topdesk.settings' => SettingsPage::class,
            ])->landingPage(function (Integration $integration): string {
                return SettingsPage::class;
            });
        });
    }

    public function boot(TicketProviderRegistry $ticketProviders): void
    {
        $ticketProviders->register('topdesk', TicketProvider::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'topdesk');
    }
}
