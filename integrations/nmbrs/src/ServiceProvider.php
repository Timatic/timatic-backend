<?php

namespace Timatic\Nmbrs;

use App\Integrations\IntegrationTypeRegistry;
use App\Models\Integration;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\Nmbrs\Commands\PushOvertimesToNmbrsCommand;
use Timatic\Nmbrs\Commands\SendOvertimeEmailsCommand;
use Timatic\Nmbrs\Commands\SyncLeaveFromNmbrsCommand;
use Timatic\Nmbrs\Filament\Pages\EmployeeMappingPage;
use Timatic\Nmbrs\Filament\Pages\OvertimeSyncPage;
use Timatic\Nmbrs\Filament\Pages\SettingsPage;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(IntegrationTypeRegistry::class, function (IntegrationTypeRegistry $types): void {
            $types->register('nmbrs', [
                'nmbrs.settings' => SettingsPage::class,
                'nmbrs.employee-mapping' => EmployeeMappingPage::class,
                'nmbrs.overtime-sync' => OvertimeSyncPage::class,
            ]);
        });
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nmbrs.php', 'nmbrs');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nmbrs');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'nmbrs');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->commands([
            PushOvertimesToNmbrsCommand::class,
            SendOvertimeEmailsCommand::class,
            SyncLeaveFromNmbrsCommand::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SyncLeaveFromNmbrsCommand::class)
                ->dailyAt('07:00')
                ->onOneServer();

            $integration = Integration::where('type', 'nmbrs')->first();

            if ($integration === null) {
                return;
            }

            $config = $integration->config ?? [];
            $day = (int) ($config['overtime_sync_day'] ?? 0);

            if (
                ($config['sync_overtime_enabled'] ?? false)
                && $day >= 1 && $day <= 28
                && filled($config['overtime_sync_time'] ?? null)
            ) {
                $schedule->command(PushOvertimesToNmbrsCommand::class)
                    ->monthlyOn($day, $config['overtime_sync_time'])
                    ->onOneServer();
            }
        });
    }
}
