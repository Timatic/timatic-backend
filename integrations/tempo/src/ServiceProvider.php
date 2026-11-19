<?php

namespace Timatic\Tempo;

use App\Integrations\IntegrationTypeRegistry;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\Tempo\Filament\Pages\SettingsPage;

class ServiceProvider extends BaseServiceProvider
{
    public const SOURCE_ID = 'tempo';

    public function register(): void
    {
        $this->callAfterResolving(IntegrationTypeRegistry::class, function (IntegrationTypeRegistry $types): void {
            $types->register('tempo', [
                'tempo.settings' => SettingsPage::class,
            ]);
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tempo');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'tempo');
    }
}
