<?php

namespace Timatic\Tempo\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Timatic\Tempo\Jobs\BackfillWorklogsJob;
use Timatic\Tempo\Jobs\SyncWorklogsJob;
use Timatic\Tempo\WorklogImporter;

/**
 * @property Schema $form
 */
class SettingsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'tempo::filament.pages.settings-page';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('tempo::tempo.settings.page_title', ['title' => $this->getIntegration()->display_title]);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $integration = $this->getIntegration();
        $config = $integration->config ?? [];

        $this->form->fill([
            'title' => $integration->title,
            'jira_integration_id' => $config['jira_integration_id'] ?? null,
            'sync_to_timatic' => (bool) ($config['sync_to_timatic'] ?? false),
            'sync_to_tempo' => (bool) ($config['sync_to_tempo'] ?? false),
            'mirrored_issues_enabled' => (bool) ($config['mirrored_issues_enabled'] ?? false),
            'mirrored_issue_prefixes' => $config['mirrored_issue_prefixes'] ?? [],
        ]);
    }

    public function form(Schema $form): Schema
    {
        $integration = $this->getIntegration();
        $config = $integration->config ?? [];
        $jiraIntegrations = Integration::query()->where('type', 'jira')->pluck('title', 'id');
        $timezone = config('timatic.preferred_timezone');

        return $form->schema([
            Section::make(__('tempo::tempo.settings.section_connection'))->schema([
                TextInput::make('title')
                    ->label(__('tempo::tempo.settings.field_title'))
                    ->autocomplete(false)
                    ->helperText(__('integrations.title_helper')),
                Callout::make(__('tempo::tempo.settings.callout_no_jira_title'))
                    ->warning()
                    ->description(__('tempo::tempo.settings.callout_no_jira_description'))
                    ->visible($jiraIntegrations->isEmpty()),
                TextInput::make('api_token')
                    ->label(__('tempo::tempo.settings.field_api_token'))
                    ->helperText(__(filled($config['api_token'] ?? null) ? 'tempo::tempo.settings.field_api_token_helper_stored' : 'tempo::tempo.settings.field_api_token_helper'))
                    ->password()
                    ->revealable()
                    ->autocomplete('off')
                    ->required(blank($config['api_token'] ?? null)),
                Select::make('jira_integration_id')
                    ->label(__('tempo::tempo.settings.field_jira_integration'))
                    ->helperText(__('tempo::tempo.settings.field_jira_integration_helper'))
                    ->options($jiraIntegrations)
                    ->required(),
            ]),
            Section::make(__('tempo::tempo.settings.section_sync'))
                ->key('worklog_sync')
                ->schema([
                    Toggle::make('sync_to_timatic')
                        ->label(__('tempo::tempo.settings.field_sync_to_timatic'))
                        ->helperText(__('tempo::tempo.settings.field_sync_to_timatic_helper')),
                    Toggle::make('sync_to_tempo')
                        ->label(__('tempo::tempo.settings.field_sync_to_tempo'))
                        ->helperText(__('tempo::tempo.settings.field_sync_to_tempo_helper')),
                    Text::make(isset($config['worklog_sync_from'], $config['worklog_synced_until'])
                        ? __('tempo::tempo.settings.sync_range', [
                            'from' => CarbonImmutable::createFromTimestampMs($config['worklog_sync_from'], $timezone)->format('d-m-Y'),
                            'until' => CarbonImmutable::createFromTimestampMs($config['worklog_synced_until'], $timezone)->format('d-m-Y H:i'),
                        ])
                        : __('tempo::tempo.settings.sync_range_empty')),
                ])
                ->footerActions([
                    Action::make('sync_worklogs')
                        ->label(__('tempo::tempo.settings.action_sync'))
                        ->disabled(! WorklogImporter::isReady($integration))
                        ->modalDescription(__('tempo::tempo.settings.action_sync_description'))
                        ->modalSubmitActionLabel(__('tempo::tempo.settings.action_sync_start'))
                        ->schema([
                            DatePicker::make('since')
                                ->label(__('tempo::tempo.settings.field_sync_since'))
                                ->helperText(__('tempo::tempo.settings.field_sync_since_helper'))
                                ->maxDate(today($timezone)),
                        ])
                        ->action(function (array $data) use ($integration, $timezone): void {
                            $months = filled($data['since'] ?? null)
                                ? BackfillWorklogsJob::dispatchMonths($integration, CarbonImmutable::parse($data['since'], $timezone))
                                : 0;
                            SyncWorklogsJob::dispatch($integration->id);

                            Notification::make()->title(trans_choice('tempo::tempo.settings.notification_sync_queued', $months))->success()->send();
                        }),
                ]),
            Section::make(__('tempo::tempo.settings.section_mirrored_issues'))->schema([
                Toggle::make('mirrored_issues_enabled')
                    ->label(__('tempo::tempo.settings.field_mirrored_issues'))
                    ->helperText(__('tempo::tempo.settings.field_mirrored_issues_helper'))
                    ->live(),
                TagsInput::make('mirrored_issue_prefixes')
                    ->label(__('tempo::tempo.settings.field_mirrored_issue_prefixes'))
                    ->helperText(__('tempo::tempo.settings.field_mirrored_issue_prefixes_helper'))
                    ->placeholder('ABC')
                    ->nestedRecursiveRules(['regex:/^[A-Z][A-Z0-9_]*$/i'])
                    ->visible(fn (Get $get): bool => (bool) $get('mirrored_issues_enabled'))
                    ->dehydratedWhenHidden(),
            ]),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->successRedirectUrl(IntegrationResource::getUrl('index')),

            Action::make('save')
                ->label(__('tempo::tempo.settings.action_save'))
                ->action(function (): void {
                    $data = $this->form->getState();
                    $integration = $this->getIntegration();
                    $config = $integration->config ?? [];

                    $integration->update([
                        'title' => $data['title'],
                        'config' => array_merge($config, [
                            'api_token' => filled($data['api_token'] ?? null) ? $data['api_token'] : ($config['api_token'] ?? null),
                            'jira_integration_id' => (int) $data['jira_integration_id'],
                            'sync_to_timatic' => (bool) ($data['sync_to_timatic'] ?? false),
                            'sync_to_tempo' => (bool) ($data['sync_to_tempo'] ?? false),
                            'mirrored_issues_enabled' => (bool) ($data['mirrored_issues_enabled'] ?? false),
                            'mirrored_issue_prefixes' => array_values(array_map('strtoupper', $data['mirrored_issue_prefixes'] ?? [])),
                        ]),
                    ]);

                    Notification::make()->title(__('tempo::tempo.settings.notification_saved'))->success()->send();
                }),
        ];
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
