<?php

namespace Timatic\Tempo;

use App\Events\EntryDeleted;
use App\Events\EntrySaved;
use App\Integrations\IntegrationTypeRegistry;
use App\Models\Integration;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\Jira\WorklogSourceRegistry;
use Timatic\Tempo\Commands\SyncWorklogsCommand;
use Timatic\Tempo\Filament\Pages\SettingsPage;
use Timatic\Tempo\Listeners\PushEntryToTempo;
use Timatic\Tempo\Listeners\RemoveEntryFromTempo;

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

        $this->commands([SyncWorklogsCommand::class]);

        Event::listen(EntrySaved::class, PushEntryToTempo::class);
        Event::listen(EntryDeleted::class, RemoveEntryFromTempo::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SyncWorklogsCommand::class)->everyFifteenMinutes()->onOneServer()->withoutOverlapping();
        });

        $this->callAfterResolving(WorklogSourceRegistry::class, function (WorklogSourceRegistry $sources): void {
            $sources->claim(fn (Integration $jira): bool => Integration::query()->where('type', 'tempo')->get()->contains(
                fn (Integration $tempo): bool => (int) ($tempo->config['jira_integration_id'] ?? 0) === $jira->id && ($tempo->config['sync_to_timatic'] ?? false),
            ));
        });
    }
}
