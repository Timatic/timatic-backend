<?php

namespace Timatic\ExactGlobe;

use App\Integrations\ExportProviderRegistry;
use App\Integrations\IntegrationTypeRegistry;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\ExactGlobe\Filament\Pages\LedgerMappingPage;

class ServiceProvider extends BaseServiceProvider
{
    public const INTEGRATION_TYPE = 'exact-globe';

    public function register(): void
    {
        $this->callAfterResolving(IntegrationTypeRegistry::class, function (IntegrationTypeRegistry $types): void {
            $types->register(self::INTEGRATION_TYPE, [
                'exact-globe.ledger-mapping' => LedgerMappingPage::class,
            ]);
        });
    }

    public function boot(ExportProviderRegistry $exportProviders): void
    {
        $exportProviders->register(self::INTEGRATION_TYPE, ExactGlobeExportProvider::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', self::INTEGRATION_TYPE);
    }
}
