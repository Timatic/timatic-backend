<?php

declare(strict_types=1);

namespace Timatic\Gmail;

use App\Integrations\IntegrationTypeRegistry;
use App\Integrations\Suggestions\SuggestionProviderRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Timatic\Gmail\Commands\SyncGmailCommand;
use Timatic\Gmail\Filament\Pages\SettingsPage;

class ServiceProvider extends BaseServiceProvider
{
    public const SOURCE_ID = 'gmail';

    public const EVENT_TYPE_EMAIL_SENT = 'email_sent';

    public const EVENT_TYPE_EMAIL_DRAFT_SAVED = 'email_draft_saved';

    public function register(): void
    {
        $this->callAfterResolving(IntegrationTypeRegistry::class, function (IntegrationTypeRegistry $types): void {
            $types->register('gmail', [
                'gmail.settings' => SettingsPage::class,
            ])->requiresConfig(['gmail.client_id', 'gmail.client_secret', 'gmail.redirect']);
        });
    }

    public function boot(SuggestionProviderRegistry $suggestionProviders): void
    {
        $suggestionProviders->register('gmail', SuggestionProvider::class);
        $this->publishes([__DIR__.'/../resources/icons/gmail.svg' => public_path('icons/gmail.svg')], 'source-icons');

        $this->mergeConfigFrom(__DIR__.'/../config/gmail.php', 'gmail');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'gmail');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'gmail');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->commands([SyncGmailCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SyncGmailCommand::class)
                ->everyFifteenMinutes()
                ->onOneServer();
        });
    }
}
