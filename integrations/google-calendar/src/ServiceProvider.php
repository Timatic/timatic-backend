<?php

namespace Timatic\GoogleCalendar;

use App\Events\SocialiteRedirecting;
use App\Integrations\IntegrationTypeRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
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
            ]);
        });
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/google_calendar.php', 'google_calendar');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'google_calendar');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->commands([SyncGoogleCalendarCommand::class]);

        Event::listen(SocialiteRedirecting::class, function (SocialiteRedirecting $event): void {
            $event->addScopes('https://www.googleapis.com/auth/calendar.readonly');
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SyncGoogleCalendarCommand::class)
                ->everyFifteenMinutes()
                ->onOneServer();
        });
    }
}
