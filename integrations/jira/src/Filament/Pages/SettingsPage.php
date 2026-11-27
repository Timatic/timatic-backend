<?php

namespace Timatic\Jira\Filament\Pages;

use App\Filament\Actions\GenerateShareLinkAction;
use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Timatic\Jira\Connector;
use Timatic\Jira\DataTransferObjects\JiraCloudResource;
use Timatic\Jira\OAuthConnector;
use Timatic\Jira\OAuthService;
use Timatic\Jira\Requests\GetMyselfRequest;
use Timatic\Jira\Requests\GetTenantInfoRequest;

/**
 * @property Schema $form
 */
class SettingsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'jira::filament.pages.jira-integration-page';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('jira::jira.settings.page_title', ['title' => $this->getIntegration()->display_title]);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $integration = $this->getIntegration();

        $this->form->fill([
            'title' => $integration->title,
        ]);

        if (session('jira_success')) {
            Notification::make()->title(session('jira_success'))->success()->send();
        }

        if (session('jira_error')) {
            Notification::make()->title(session('jira_error'))->danger()->send();
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
            NavigationItem::make(__('jira::jira.settings.nav_label'))
                ->url(SettingsPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === SettingsPage::getUrl(['record' => $record])),
            NavigationItem::make(__('jira::jira.project_mapping.nav_label'))
                ->url(ProjectMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === ProjectMappingPage::getUrl(['record' => $record])),
            NavigationItem::make(__('jira::jira.epic_mapping.nav_label'))
                ->url(EpicMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === EpicMappingPage::getUrl(['record' => $record])),
            NavigationItem::make(__('jira::jira.user_mapping.nav_label'))
                ->url(UserMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === UserMappingPage::getUrl(['record' => $record])),
        ];
    }

    public function form(Schema $form): Schema
    {
        $config = $this->getRecord()->config ?? [];

        if ($this->isPendingSiteSelection($config)) {
            $rawResources = $config['pending_resources'] ?? [];
            $pendingResources = is_array($rawResources) ? $rawResources : [];
            $options = collect($pendingResources)
                ->mapWithKeys(fn (array $resource) => [$resource['id'] => $resource['url']])
                ->all();

            return $form->schema([
                Callout::make(__('jira::jira.settings.callout_select_site_title'))
                    ->warning()
                    ->description(__('jira::jira.settings.callout_select_site_description')),
                Select::make('selectedCloudId')
                    ->label(__('jira::jira.settings.field_select_site'))
                    ->options($options)
                    ->required(),
            ])->statePath('data');
        }

        if (! Connector::hasCredentials($config)) {
            return $form->schema([
                Callout::make(__('jira::jira.settings.callout_disconnected_title'))
                    ->warning()
                    ->description(__('jira::jira.settings.callout_disconnected_description')),
            ])->statePath('data');
        }

        return $form->schema([
            TextInput::make('title')
                ->label(__('jira::jira.common.field_title'))
                ->autocomplete(false)
                ->helperText(__('integrations.title_helper')),
            Callout::make(__('jira::jira.settings.callout_connected_title'))
                ->success()
                ->description(__('jira::jira.settings.callout_connected_description', ['cloud_url' => $config['cloud_url'] ?? ''])),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        $config = $this->getRecord()->config ?? [];

        $isConnected = Connector::hasCredentials($config);
        $isPending = $this->isPendingSiteSelection($config);

        return [
            ActionGroup::make([
                GenerateShareLinkAction::make('jira::jira', 'jira.delegate.show'),

                Action::make('disconnect')
                    ->label(__('jira::jira.common.action_disconnect'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        app(OAuthService::class)->disconnect($this->getIntegration());
                        $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                    })
                    ->visible($isConnected || $isPending),

                DeleteAction::make()
                    ->successRedirectUrl(IntegrationResource::getUrl('index')),
            ]),

            Action::make('connect')
                ->label(__('jira::jira.settings.action_connect'))
                ->url(route('jira.oauth.redirect', $this->getRecord()))
                ->visible(! $isConnected && ! $isPending),

            Action::make('connectWithToken')
                ->label(__('jira::jira.settings.action_connect_token'))
                ->color('gray')
                ->modalDescription(__('jira::jira.settings.connect_token_description'))
                ->schema([
                    TextInput::make('site_url')
                        ->label(__('jira::jira.settings.field_site_url'))
                        ->placeholder('https://acme.atlassian.net')
                        ->regex('/^https:\/\/[a-z0-9-]+\.atlassian\.net\/?$/i')
                        ->required(),
                    TextInput::make('email')
                        ->label(__('jira::jira.settings.field_email'))
                        ->helperText(__('jira::jira.settings.field_email_helper'))
                        ->email()
                        ->required(),
                    TextInput::make('api_token')
                        ->label(__('jira::jira.settings.field_api_token'))
                        ->helperText(__('jira::jira.settings.field_api_token_helper'))
                        ->password()
                        ->revealable()
                        ->autocomplete('off')
                        ->required(),
                ])
                ->action(function (array $data, Action $action): void {
                    $tenantInfo = new OAuthConnector()->send(new GetTenantInfoRequest(rtrim($data['site_url'], '/')));

                    if ($tenantInfo->failed()) {
                        Notification::make()->title(__('jira::jira.settings.notification_site_not_found'))->danger()->send();
                        $action->halt();
                    }

                    /** @var JiraCloudResource $site */
                    $site = $tenantInfo->dto();
                    $credentials = [
                        'email' => $data['email'],
                        'api_token' => $data['api_token'],
                        'cloud_id' => $site->id,
                        'cloud_url' => $site->url,
                    ];

                    if (new Connector($credentials)->send(new GetMyselfRequest)->failed()) {
                        Notification::make()->title(__('jira::jira.settings.notification_token_rejected'))->danger()->send();
                        $action->halt();
                    }

                    $integration = $this->getIntegration();
                    $integration->update(['config' => array_merge($integration->config ?? [], $credentials)]);
                    session()->flash('jira_success', __('jira::jira.settings.notification_connected'));
                    $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                })
                ->visible(! $isConnected && ! $isPending),

            Action::make('confirmSite')
                ->label(__('jira::jira.settings.action_confirm_site'))
                ->action(function (): void {
                    $state = $this->form->getState();
                    $cloudId = $state['selectedCloudId'] ?? null;

                    if (! $cloudId) {
                        Notification::make()->title(__('jira::jira.settings.notification_select_site_required'))->danger()->send();

                        return;
                    }

                    app(OAuthService::class)->confirmResource($this->getIntegration(), $cloudId);
                    session()->flash('jira_success', __('jira::jira.settings.notification_connected'));
                    $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                })
                ->visible($isPending),

            Action::make('save')
                ->label(__('jira::jira.common.action_save'))
                ->action(function (): void {
                    $data = $this->form->getState();
                    $integration = $this->getIntegration();

                    $integration->update(['title' => $data['title']]);

                    Notification::make()->title(__('jira::jira.settings.notification_saved'))->success()->send();
                })
                ->visible($isConnected),
        ];
    }

    /** @param array<string, mixed> $config */
    private function isPendingSiteSelection(array $config): bool
    {
        return filled($config['access_token'] ?? null) && ! empty($config['pending_resources'] ?? []);
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
