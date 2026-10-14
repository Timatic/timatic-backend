<?php

declare(strict_types=1);

namespace Timatic\GoogleCalendar;

use App\Integrations\IntegrationTypeRegistry;
use App\Integrations\SuggestionIntegrationRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\GoogleCalendar\Commands\SyncGoogleCalendarCommand;
use Timatic\GoogleCalendar\Filament\Pages\SettingsPage;

class ServiceProvider extends BaseServiceProvider
{
    public const SOURCE_ID = 'google_calendar';

    public const EVENT_TYPE_CALENDAR_EVENT_STARTED = 'calendar_event_started';

    public function register(): void
    {
        $this->callAfterResolving(IntegrationTypeRegistry::class, function (IntegrationTypeRegistry $types): void {
            $types->register('google_calendar', [
                'google_calendar.settings' => SettingsPage::class,
            ])->requiresConfig(['google_calendar.client_id', 'google_calendar.client_secret']);
        });
    }

    public function boot(SuggestionIntegrationRegistry $suggestionIntegrations): void
    {
        $suggestionIntegrations->register('google_calendar', SuggestionIntegration::class);

        $this->mergeConfigFrom(__DIR__.'/../config/google_calendar.php', 'google_calendar');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'google_calendar');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'google_calendar');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->commands([SyncGoogleCalendarCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SyncGoogleCalendarCommand::class)
                ->everyFifteenMinutes()
                ->onOneServer();
        });
    }
}
