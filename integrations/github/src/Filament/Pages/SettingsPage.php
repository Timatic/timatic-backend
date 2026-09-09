<?php

namespace Timatic\GitHub\Filament\Pages;

use App\Filament\Actions\GenerateShareLinkAction;
use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Timatic\GitHub\Exceptions\GitHubException;
use Timatic\GitHub\InstallationService;
use Timatic\GitHub\OAuthService;

/**
 * @property Schema $form
 */
class SettingsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'github::filament.pages.github-settings-page';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('github::github.settings.page_title', ['name' => $this->getIntegration()->name]);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill(['name' => $this->getIntegration()->name]);

        if (session('github_success')) {
            Notification::make()->title(session('github_success'))->success()->send();
        }

        if (session('github_error')) {
            Notification::make()->title(session('github_error'))->danger()->send();
        }
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Start;
    }

    public function getSubNavigation(): array
    {
        $record = $this->getRecord();

        return [
            NavigationItem::make(__('github::github.settings.nav_label'))
                ->url(SettingsPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === SettingsPage::getUrl(['record' => $record])),
            NavigationItem::make(__('github::github.repository_mapping.nav_label'))
                ->url(RepositoryMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === RepositoryMappingPage::getUrl(['record' => $record])),
        ];
    }

    public function form(Schema $form): Schema
    {
        $config = $this->getRecord()->config ?? [];

        if (! $this->isConfigured()) {
            return $form->schema([
                Callout::make(__('github::github.settings.callout_not_configured_title'))
                    ->danger()
                    ->description(__('github::github.settings.callout_not_configured_description')),
            ])->statePath('data');
        }

        if (! $this->hasTokens($config)) {
            return $form->schema([
                Callout::make(__('github::github.settings.callout_disconnected_title'))
                    ->warning()
                    ->description(__('github::github.settings.callout_disconnected_description')),
            ])->statePath('data');
        }

        if (! $this->hasInstallations($config)) {
            return $form->schema([
                Callout::make(__('github::github.settings.callout_no_installations_title'))
                    ->info()
                    ->description(__('github::github.settings.callout_no_installations_description')),
            ])->statePath('data');
        }

        return $form->schema([
            TextInput::make('name')
                ->label(__('github::github.common.field_name'))
                ->required(),
            Callout::make(__('github::github.settings.callout_connected_title'))
                ->success()
                ->description(__('github::github.settings.callout_connected_description', [
                    'accounts' => implode(', ', $this->installations($config)),
                    'routing' => $this->proxyRoutingLine($config),
                ])),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        $config = $this->getRecord()->config ?? [];

        return [
            ActionGroup::make([
                GenerateShareLinkAction::make('github::github', 'github.delegate.show'),

                Action::make('disconnect')
                    ->label(__('github::github.common.action_disconnect'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        app(OAuthService::class)->disconnect($this->getIntegration());
                        $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                    })
                    ->visible($this->hasTokens($config)),
            ]),

            Action::make('install_app')
                ->label(__('github::github.settings.action_install_app'))
                ->url(app(OAuthService::class)->installUrl())
                ->openUrlInNewTab()
                ->visible($this->hasTokens($config)),

            Action::make('refresh_installations')
                ->label(__('github::github.settings.action_refresh_installations'))
                ->action(function (): void {
                    try {
                        app(InstallationService::class)->refresh($this->getIntegration());
                    } catch (GitHubException $e) {
                        Notification::make()
                            ->title(__('github::github.settings.notification_installations_failed', ['message' => $e->getMessage()]))
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                })
                ->visible($this->hasTokens($config)),

            Action::make('connect')
                ->label(__('github::github.settings.action_connect'))
                ->url(route('github.oauth.redirect', $this->getRecord()))
                ->visible($this->isConfigured() && ! $this->hasTokens($config)),

            Action::make('save')
                ->label(__('github::github.common.action_save'))
                ->action(function (): void {
                    $data = $this->form->getState();
                    $this->getIntegration()->update(['name' => $data['name']]);
                    Notification::make()->title(__('github::github.common.notification_name_changed'))->success()->send();
                })
                ->visible($this->hasInstallations($config)),
        ];
    }

    /** @param array<string, mixed> $config
     * @return array<int, string> */
    private function installations(array $config): array
    {
        return app(InstallationService::class)->stored($config);
    }

    /**
     * The auth proxy routes deliveries by installation, using a hand-maintained map.
     *
     * @param  array<string, mixed>  $config
     */
    private function proxyRoutingLine(array $config): string
    {
        return collect($this->installations($config))
            ->keys()
            ->map(fn (int $installationId) => implode(':', [
                $installationId,
                config('timatic.tenant_slug'),
                $this->getRecord()->getKey(),
            ]))
            ->implode(',');
    }

    private function isConfigured(): bool
    {
        return filled(config('github.client_id'))
            && filled(config('github.client_secret'))
            && filled(config('github.app_id'))
            && filled(config('github.private_key'));
    }

    /** @param array<string, mixed> $config */
    private function hasTokens(array $config): bool
    {
        return $this->isConfigured() && filled($config['access_token'] ?? null);
    }

    /** @param array<string, mixed> $config */
    private function hasInstallations(array $config): bool
    {
        return $this->hasTokens($config) && $this->installations($config) !== [];
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
