<?php

namespace Timatic\Topdesk;

use App\Integrations\IntegrationTypeRegistry;
use App\Integrations\TicketProviderRegistry;
use App\Models\Integration;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\Topdesk\Filament\Pages\SettingsPage;
use Timatic\Topdesk\Services\TopdeskBranchResolver;

class ServiceProvider extends BaseServiceProvider
{
    public const SOURCE_ID = 'topdesk';

    public const INTEGRATION_TYPE = 'topdesk';

    public function register(): void
    {
        $this->app->bind(ApiCredentials::class, function (): ApiCredentials {
            $integration = Integration::where('type', self::INTEGRATION_TYPE)->first();

            return new ApiCredentials(
                baseUrl: $integration?->config['base_url'] ?? '',
                username: $integration?->config['username'] ?? '',
                apiToken: $integration?->config['api_token'] ?? '',
            );
        });

        $this->app->bind(TopdeskBranchResolver::class, function (Application $app): TopdeskBranchResolver {
            $integration = Integration::where('type', self::INTEGRATION_TYPE)->first();

            return new TopdeskBranchResolver(
                connector: $app->make(Connector::class),
                baseUrl: $integration?->config['base_url'] ?? '',
                branchMatchField: $integration?->config['branch_match_field'] ?? 'clientReferenceNumber',
            );
        });

        $this->callAfterResolving(IntegrationTypeRegistry::class, function (IntegrationTypeRegistry $types): void {
            $types->register(self::INTEGRATION_TYPE, [
                'topdesk.settings' => SettingsPage::class,
            ])->landingPage(function (Integration $integration): string {
                return SettingsPage::class;
            });
        });
    }

    public function boot(TicketProviderRegistry $ticketProviders): void
    {
        $ticketProviders->register(self::INTEGRATION_TYPE, IncidentTicketProvider::class);
        $ticketProviders->register(self::INTEGRATION_TYPE, ChangeTicketProvider::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', self::INTEGRATION_TYPE);
    }
}
