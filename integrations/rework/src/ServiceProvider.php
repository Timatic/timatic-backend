<?php

namespace Timatic\Rework;

use App\Integrations\IntegrationTypeRegistry;
use App\Models\Integration;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\Rework\Commands\SyncLeaveFromReworkCommand;
use Timatic\Rework\Filament\Pages\SettingsPage;
use Timatic\Rework\Filament\Pages\UserMappingPage;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApiKey::class, function (): ApiKey {
            $integration = Integration::where('type', 'rework')->first();

            return new ApiKey($integration?->config['api_key'] ?? '');
        });

        $this->app->bind(CompanyId::class, function (): CompanyId {
            $integration = Integration::where('type', 'rework')->first();

            return new CompanyId($integration?->config['company_id'] ?? '');
        });

        $this->callAfterResolving(IntegrationTypeRegistry::class, function (IntegrationTypeRegistry $types): void {
            $types->register('rework', [
                'rework.settings' => SettingsPage::class,
                'rework.user-mapping' => UserMappingPage::class,
            ]);
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'rework');

        $this->commands([
            SyncLeaveFromReworkCommand::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SyncLeaveFromReworkCommand::class)
                ->dailyAt('07:00')
                ->onOneServer();
        });
    }
}
