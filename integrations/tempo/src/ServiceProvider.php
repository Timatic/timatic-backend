<?php

namespace Timatic\Tempo;

use App\Integrations\IntegrationTypeRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\Tempo\Commands\SyncWorklogsCommand;
use Timatic\Tempo\Filament\Pages\SettingsPage;
use Timatic\Tempo\Listeners\EntrySubscriber;

class ServiceProvider extends BaseServiceProvider
{
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

        $this->commands([SyncWorklogsCommand::class]);

        Event::subscribe(EntrySubscriber::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SyncWorklogsCommand::class)->everyFifteenMinutes()->onOneServer()->withoutOverlapping();
        });
    }
}
