<?php

namespace Timatic\Jira\Filament\Pages;

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
use Timatic\Jira\OAuthService;

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
        return __('jira::jira.settings.page_title', ['name' => $this->getIntegration()->name]);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill(['name' => $this->getIntegration()->name]);

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
        ];
    }

    public function form(Schema $form): Schema
    {
        $config = $this->getRecord()->config ?? [];

        if (! $this->isConfigured()) {
            return $form->schema([
                Callout::make(__('jira::jira.settings.callout_not_configured_title'))
                    ->danger()
                    ->description(__('jira::jira.settings.callout_not_configured_description')),
            ])->statePath('data');
        }

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

        if (! $this->isConnected($config)) {
            return $form->schema([
                Callout::make(__('jira::jira.settings.callout_disconnected_title'))
                    ->warning()
                    ->description(__('jira::jira.settings.callout_disconnected_description')),
            ])->statePath('data');
        }

        return $form->schema([
            TextInput::make('name')
                ->label(__('jira::jira.common.field_name'))
                ->required(),
            Callout::make(__('jira::jira.settings.callout_connected_title'))
                ->success()
                ->description(__('jira::jira.settings.callout_connected_description', ['cloud_url' => $config['cloud_url'] ?? ''])),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        $config = $this->getRecord()->config ?? [];

        $isPending = $this->isPendingSiteSelection($config);

        return [
            Action::make('disconnect')
                ->label(__('jira::jira.common.action_disconnect'))
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(OAuthService::class)->disconnect($this->getIntegration());
                    $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                })
                ->visible($this->isConnected($config) || $isPending),

            Action::make('connect')
                ->label(__('jira::jira.settings.action_connect'))
                ->url(route('jira.oauth.redirect', $this->getRecord()))
                ->visible($this->isConfigured() && ! $this->isConnected($config) && ! $isPending),

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
                    $this->getIntegration()->update(['name' => $data['name']]);
                    Notification::make()->title(__('jira::jira.common.notification_name_changed'))->success()->send();
                })
                ->visible($this->isConnected($config)),
        ];
    }

    private function isConfigured(): bool
    {
        return filled(config('jira.client_id')) && filled(config('jira.client_secret'));
    }

    /** @param array<string, mixed> $config */
    private function isConnected(array $config): bool
    {
        return $this->isConfigured() && filled($config['access_token'] ?? null) && filled($config['cloud_id'] ?? null);
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
