<?php

namespace Timatic\Topdesk\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * @property Schema $form
 */
class SettingsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'topdesk::filament.pages.settings-page';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return $this->getIntegration()->display_title;
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Start;
    }

    public function getSubNavigation(): array
    {
        $record = $this->getRecord();

        return [
            NavigationItem::make(__('topdesk::topdesk.settings.nav_label'))
                ->url(SettingsPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === SettingsPage::getUrl(['record' => $record])),
        ];
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $config = $this->getIntegration()->config ?? [];

        $this->form->fill([
            'title' => $this->getIntegration()->title,
            'base_url' => $config['base_url'] ?? null,
            'username' => $config['username'] ?? null,
            'api_token' => $config['api_token'] ?? null,
            'branch_match_field' => $config['branch_match_field'] ?? 'clientReferenceNumber',
            'incident_key_pattern' => $config['incident_key_pattern'] ?? '[A-Z]+\s?\d+',
            'change_key_pattern' => $config['change_key_pattern'] ?? '[A-Z]+\s?\d+',
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('title')
                ->label(__('topdesk::topdesk.settings.field_title'))
                ->autocomplete(false)
                ->helperText(__('integrations.title_helper')),

            Section::make(__('topdesk::topdesk.settings.section_connection'))
                ->schema([
                    TextInput::make('base_url')
                        ->label(__('topdesk::topdesk.settings.field_base_url'))
                        ->placeholder('https://company.topdesk.net')
                        ->url()
                        ->required(),

                    TextInput::make('username')
                        ->label(__('topdesk::topdesk.settings.field_username'))
                        ->required(),

                    TextInput::make('api_token')
                        ->label(__('topdesk::topdesk.settings.field_api_token'))
                        ->password()
                        ->revealable()
                        ->required(),
                ]),

            Section::make(__('topdesk::topdesk.settings.section_advanced'))
                ->schema([
                    TextInput::make('branch_match_field')
                        ->label(__('topdesk::topdesk.settings.field_branch_match_field'))
                        ->default('clientReferenceNumber')
                        ->helperText(__('topdesk::topdesk.settings.field_branch_match_field_helper'))
                        ->required(),

                    TextInput::make('incident_key_pattern')
                        ->label(__('topdesk::topdesk.settings.field_incident_key_pattern'))
                        ->default('[A-Z]+\s?\d+')
                        ->helperText(__('topdesk::topdesk.settings.field_incident_key_pattern_helper'))
                        ->required(),

                    TextInput::make('change_key_pattern')
                        ->label(__('topdesk::topdesk.settings.field_change_key_pattern'))
                        ->default('[A-Z]+\s?\d+')
                        ->helperText(__('topdesk::topdesk.settings.field_change_key_pattern_helper'))
                        ->required(),
                ]),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label(__('topdesk::topdesk.settings.action_delete'))
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->getIntegration()->delete();
                    $this->redirect(IntegrationResource::getUrl('index'));
                }),

            Action::make('save')
                ->label(__('topdesk::topdesk.settings.action_save'))
                ->action(function (): void {
                    $data = $this->form->getState();

                    $config = array_merge($this->getIntegration()->config ?? [], [
                        'base_url' => $data['base_url'],
                        'username' => $data['username'],
                        'api_token' => $data['api_token'],
                        'branch_match_field' => $data['branch_match_field'],
                        'incident_key_pattern' => $data['incident_key_pattern'],
                        'change_key_pattern' => $data['change_key_pattern'],
                    ]);

                    $this->getIntegration()->update([
                        'title' => $data['title'],
                        'config' => $config,
                    ]);

                    Notification::make()->title(__('topdesk::topdesk.settings.notification_saved'))->success()->send();
                }),
        ];
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
