<?php

namespace Timatic\GitHub\Filament\Pages;

use App\Filament\Actions\GenerateShareLinkAction;
use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Timatic\GitHub\Connector;
use Timatic\GitHub\DataTransferObjects\GitHubInstallation;
use Timatic\GitHub\OAuthService;
use Timatic\GitHub\Requests\GetUserInstallationsRequest;

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

        if (! $this->hasInstallation($config)) {
            return $form->schema([
                Callout::make(__('github::github.settings.callout_choose_installation_title'))
                    ->info()
                    ->description(__('github::github.settings.callout_choose_installation_description')),
            ])->statePath('data');
        }

        return $form->schema([
            TextInput::make('name')
                ->label(__('github::github.common.field_name'))
                ->required(),
            Callout::make(__('github::github.settings.callout_connected_title'))
                ->success()
                ->description(__('github::github.settings.callout_connected_description', [
                    'account' => $config['installation_account'] ?? '',
                    'installation' => $config['installation_id'] ?? '',
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
                ->visible($this->hasTokens($config) && ! $this->hasInstallation($config)),

            Action::make('choose_installation')
                ->label(__('github::github.settings.action_choose_installation'))
                ->schema([
                    Select::make('installation_id')
                        ->label(__('github::github.settings.installation_select_label'))
                        ->options(fn () => $this->installationOptions())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $integration = $this->getIntegration();
                    $installationId = (int) $data['installation_id'];

                    $integration->update([
                        'config' => array_merge($integration->config ?? [], [
                            'installation_id' => $installationId,
                            'installation_account' => $this->installationOptions()[$installationId] ?? '',
                        ]),
                    ]);

                    session()->flash('github_success', __('github::github.settings.notification_installation_chosen'));
                    $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                })
                ->visible($this->hasTokens($config) && ! $this->hasInstallation($config)),

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
                ->visible($this->hasInstallation($config)),
        ];
    }

    /** @return array<int, string> */
    private function installationOptions(): array
    {
        $integration = app(OAuthService::class)->refreshIfExpired($this->getIntegration());
        $response = Connector::forUser($integration->config['access_token'] ?? '')
            ->send(new GetUserInstallationsRequest);

        if ($response->failed()) {
            Notification::make()
                ->title(__('github::github.settings.notification_installations_failed', ['status' => $response->status()]))
                ->danger()
                ->persistent()
                ->send();

            return [];
        }

        /** @var array<int, GitHubInstallation> $installations */
        $installations = $response->dto();

        return collect($installations)
            ->mapWithKeys(fn (GitHubInstallation $installation) => [
                $installation->id => $installation->accountLogin.' ('.$installation->accountType.')',
            ])
            ->all();
    }

    /**
     * The auth proxy routes deliveries by installation, using a hand-maintained map.
     *
     * @param  array<string, mixed>  $config
     */
    private function proxyRoutingLine(array $config): string
    {
        return implode(':', [
            $config['installation_id'] ?? '',
            config('timatic.tenant_slug'),
            $this->getRecord()->getKey(),
        ]);
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
    private function hasInstallation(array $config): bool
    {
        return $this->hasTokens($config) && filled($config['installation_id'] ?? null);
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
