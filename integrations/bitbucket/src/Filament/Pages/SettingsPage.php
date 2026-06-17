<?php

namespace Timatic\Bitbucket\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Timatic\Bitbucket\Connector;
use Timatic\Bitbucket\DataTransferObjects\BitbucketWebhook;
use Timatic\Bitbucket\DataTransferObjects\BitbucketWorkspace;
use Timatic\Bitbucket\OAuthService;
use Timatic\Bitbucket\Requests\DeleteWorkspaceWebhookRequest;
use Timatic\Bitbucket\Requests\GetWorkspacesRequest;
use Timatic\Bitbucket\Requests\RegisterWorkspaceWebhookRequest;

/**
 * @property Schema $form
 */
class SettingsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'bitbucket::filament.pages.bitbucket-settings-page';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('bitbucket::bitbucket.settings.page_title', ['name' => $this->getIntegration()->name]);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill(['name' => $this->getIntegration()->name]);

        if (session('bitbucket_success')) {
            Notification::make()->title(session('bitbucket_success'))->success()->send();
        }

        if (session('bitbucket_error')) {
            Notification::make()->title(session('bitbucket_error'))->danger()->send();
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
            NavigationItem::make(__('bitbucket::bitbucket.settings.nav_label'))
                ->url(SettingsPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === SettingsPage::getUrl(['record' => $record])),
            NavigationItem::make(__('bitbucket::bitbucket.repository_mapping.nav_label'))
                ->url(RepositoryMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === RepositoryMappingPage::getUrl(['record' => $record])),
        ];
    }

    public function form(Schema $form): Schema
    {
        $config = $this->getRecord()->config ?? [];

        if (! $this->isConfigured()) {
            return $form->schema([
                Callout::make(__('bitbucket::bitbucket.settings.callout_not_configured_title'))
                    ->danger()
                    ->description(__('bitbucket::bitbucket.settings.callout_not_configured_description')),
            ])->statePath('data');
        }

        if (! $this->hasTokens($config)) {
            return $form->schema([
                Callout::make(__('bitbucket::bitbucket.settings.callout_disconnected_title'))
                    ->warning()
                    ->description(__('bitbucket::bitbucket.settings.callout_disconnected_description')),
            ])->statePath('data');
        }

        if (! $this->hasWorkspace($config)) {
            return $form->schema([
                Callout::make(__('bitbucket::bitbucket.settings.callout_choose_workspace_title'))
                    ->info()
                    ->description(__('bitbucket::bitbucket.settings.callout_choose_workspace_description')),
            ])->statePath('data');
        }

        if (! $this->hasWebhook($config)) {
            return $form->schema([
                Callout::make(__('bitbucket::bitbucket.settings.callout_webhook_missing_title'))
                    ->warning()
                    ->description(__('bitbucket::bitbucket.settings.callout_webhook_missing_description')),
            ])->statePath('data');
        }

        return $form->schema([
            TextInput::make('name')
                ->label(__('bitbucket::bitbucket.common.field_name'))
                ->required(),
            Callout::make(__('bitbucket::bitbucket.settings.callout_connected_title'))
                ->success()
                ->description(__('bitbucket::bitbucket.settings.callout_connected_description', ['workspace' => $config['workspace'] ?? ''])),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        $config = $this->getRecord()->config ?? [];

        return [
            Action::make('disconnect')
                ->label(__('bitbucket::bitbucket.common.action_disconnect'))
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => app(OAuthService::class)->disconnect($this->getIntegration()))
                ->visible($this->hasWorkspace($config)),

            Action::make('delete_webhook')
                ->label(__('bitbucket::bitbucket.settings.action_delete_webhook'))
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $integration = app(OAuthService::class)->refreshIfExpired($this->getIntegration());
                    $config = $integration->config ?? [];

                    $response = (new Connector($config))
                        ->send(new DeleteWorkspaceWebhookRequest(
                            $config['workspace'],
                            $config['webhook_uuid'],
                        ));

                    if ($response->successful()) {
                        $integration->update([
                            'config' => array_diff_key($config, array_flip(['webhook_uuid', 'webhook_secret'])),
                        ]);

                        session()->flash('bitbucket_success', __('bitbucket::bitbucket.settings.notification_webhook_deleted'));
                        $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                    } else {
                        $error = $response->json('error.message') ?? $response->json('error') ?? $response->body();
                        Notification::make()
                            ->title(__('bitbucket::bitbucket.settings.notification_delete_webhook_failed', ['status' => $response->status()]))
                            ->body(is_string($error) ? mb_substr($error, 0, 300) : null)
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                })
                ->visible($this->hasWebhook($config)),

            Action::make('install_webhook')
                ->label(__('bitbucket::bitbucket.settings.action_install_webhook'))
                ->action(function (): void {
                    $integration = app(OAuthService::class)->refreshIfExpired($this->getIntegration());
                    $config = $integration->config ?? [];
                    $webhookUrl = rtrim(config('app.admin_url'), '/').'/integrations/bitbucket/webhook/'.$integration->id;

                    $webhookSecret = $config['webhook_secret'] ?? Str::random(32);

                    if (! isset($config['webhook_secret'])) {
                        $config['webhook_secret'] = $webhookSecret;
                        $integration->update(['config' => $config]);
                    }

                    $response = (new Connector($config))
                        ->send(new RegisterWorkspaceWebhookRequest(
                            $config['workspace'],
                            $webhookUrl,
                            $webhookSecret,
                        ));

                    if ($response->successful()) {
                        /** @var BitbucketWebhook $webhook */
                        $webhook = $response->dto();

                        $integration->update([
                            'config' => array_merge($config, ['webhook_uuid' => $webhook->uuid]),
                        ]);

                        session()->flash('bitbucket_success', __('bitbucket::bitbucket.settings.notification_webhook_installed'));
                        $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                    } else {
                        $error = $response->json('error.message') ?? $response->json('error') ?? $response->body();
                        Notification::make()
                            ->title(__('bitbucket::bitbucket.settings.notification_install_webhook_failed', ['status' => $response->status()]))
                            ->body(is_string($error) ? mb_substr($error, 0, 300) : null)
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                })
                ->visible($this->hasWorkspace($config) && ! $this->hasWebhook($config)),

            Action::make('choose_workspace')
                ->label(__('bitbucket::bitbucket.settings.action_choose_workspace'))
                ->form([
                    Select::make('workspace')
                        ->label(__('bitbucket::bitbucket.settings.workspace_select_label'))
                        ->options(function () {
                            $integration = app(OAuthService::class)->refreshIfExpired($this->getIntegration());
                            $response = (new Connector($integration->config ?? []))
                                ->send(new GetWorkspacesRequest);

                            /** @var array<int, BitbucketWorkspace> $workspaces */
                            $workspaces = $response->dto() ?? [];

                            return collect($workspaces)
                                ->mapWithKeys(fn (BitbucketWorkspace $w) => [$w->slug => $w->name]);
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $integration = $this->getIntegration();
                    $integration->update([
                        'config' => array_merge($integration->config ?? [], [
                            'workspace' => $data['workspace'],
                            'webhook_secret' => $integration->config['webhook_secret'] ?? Str::random(32),
                        ]),
                    ]);

                    $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                })
                ->visible($this->hasTokens($config) && ! $this->hasWorkspace($config)),

            Action::make('connect')
                ->label(__('bitbucket::bitbucket.settings.action_connect'))
                ->url(route('bitbucket.oauth.redirect', $this->getRecord()))
                ->visible($this->isConfigured() && ! $this->hasTokens($config)),

            Action::make('save')
                ->label(__('bitbucket::bitbucket.common.action_save'))
                ->action(function (): void {
                    $data = $this->form->getState();
                    $this->getIntegration()->update(['name' => $data['name']]);
                    Notification::make()->title(__('bitbucket::bitbucket.common.notification_name_changed'))->success()->send();
                }),
        ];
    }

    private function isConfigured(): bool
    {
        return filled(config('bitbucket.client_id')) && filled(config('bitbucket.client_secret'));
    }

    /** @param array<string, mixed> $config */
    private function hasTokens(array $config): bool
    {
        return $this->isConfigured() && filled($config['access_token'] ?? null);
    }

    /** @param array<string, mixed> $config */
    private function hasWorkspace(array $config): bool
    {
        return $this->hasTokens($config) && filled($config['workspace'] ?? null);
    }

    /** @param array<string, mixed> $config */
    private function hasWebhook(array $config): bool
    {
        return $this->hasWorkspace($config) && filled($config['webhook_uuid'] ?? null);
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
