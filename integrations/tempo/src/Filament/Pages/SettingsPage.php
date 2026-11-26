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
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Timatic\Jira\Filament\Pages\EpicMappingPage;
use Timatic\Jira\Filament\Pages\ProjectMappingPage;
use Timatic\Jira\Filament\Pages\UserMappingPage;
use Timatic\Jira\JiraDirectory;
use Timatic\Jira\Models\EpicMapping;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\Models\UserMapping;
use Timatic\Tempo\Jobs\BackfillWorklogsJob;
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
        $jiraIntegrations = Integration::query()->where('type', 'jira')->get()->pluck('display_title', 'id');
        $timezone = config('timatic.preferred_timezone');
        // A date string: the pickers would shift a date object to the app timezone, which is a day earlier
        $today = CarbonImmutable::today($timezone)->toDateString();
        $jiraId = (int) ($config['jira_integration_id'] ?? 0);
        // The import remembers every mapping it skipped a worklog for; the ones mapped since drop out here
        $unmappedUserIds = UserMapping::query()
            ->whereKey($config['worklog_skipped_user_mapping_ids'] ?? [])
            ->where('integration_id', $jiraId)
            ->whereNull('user_id')
            ->pluck('id');
        // A worklog without a mapped epic falls back to its project, so mapping that project settles the epic too
        $mappedProjectKeys = ProjectMapping::active()->where('integration_id', $jiraId)->whereNotNull('customer_id')->whereNotNull('budget_id')->pluck('project_key');
        $unmappedEpicIds = EpicMapping::query()
            ->whereKey($config['worklog_skipped_epic_mapping_ids'] ?? [])
            ->where('integration_id', $jiraId)
            ->where(fn (Builder $query) => $query->whereNull('customer_id')->orWhereNull('budget_id'))
            ->get(['id', 'epic_key'])
            ->reject(fn (EpicMapping $epic): bool => $mappedProjectKeys->contains(Str::before($epic->epic_key, '-')))
            ->pluck('id');
        // A mirrored issue is booked on the project of another Jira instance, so the projects are listed per instance
        $unmappedProjectIds = ProjectMapping::active()
            ->whereKey($config['worklog_skipped_project_mapping_ids'] ?? [])
            ->where(fn (Builder $query) => $query->whereNull('customer_id')->orWhereNull('budget_id'))
            ->get(['id', 'integration_id'])
            ->groupBy('integration_id');

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
                Callout::make(__('tempo::tempo.settings.callout_jira_disconnected_title'))
                    ->warning()
                    ->description(__('tempo::tempo.settings.callout_jira_disconnected_description'))
                    ->visible(function (): bool {
                        $jiraId = $this->getIntegration()->config['jira_integration_id'] ?? null;

                        return filled($jiraId) && ! app(JiraDirectory::class)->isConnected(Integration::find((int) $jiraId));
                    }),
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
                    Text::make(isset($config['worklog_synced_until'])
                        ? __('tempo::tempo.settings.last_synced', ['at' => CarbonImmutable::createFromTimestampMs($config['worklog_synced_until'], $timezone)->format('d-m-Y H:i')])
                        : __('tempo::tempo.settings.last_synced_never')),
                    Callout::make(__('tempo::tempo.settings.callout_skipped_title'))
                        ->warning()
                        ->description(__('tempo::tempo.settings.callout_skipped_description'))
                        ->visible($unmappedUserIds->isNotEmpty() || $unmappedEpicIds->isNotEmpty() || $unmappedProjectIds->isNotEmpty())
                        ->footer([
                            Action::make('map_users')
                                ->label(trans_choice('tempo::tempo.settings.action_map_users', $unmappedUserIds->count()))
                                ->link()
                                ->url(UserMappingPage::getUrl(['record' => $jiraId, 'filters' => ['id' => ['ids' => $unmappedUserIds->implode(',')]]]))
                                ->visible($unmappedUserIds->isNotEmpty()),
                            Action::make('map_epics')
                                ->label(trans_choice('tempo::tempo.settings.action_map_epics', $unmappedEpicIds->count()))
                                ->link()
                                ->url(EpicMappingPage::getUrl(['record' => $jiraId, 'filters' => ['id' => ['ids' => $unmappedEpicIds->implode(',')]]]))
                                ->visible($unmappedEpicIds->isNotEmpty()),
                            ...$unmappedProjectIds->map(fn (Collection $projects, int $projectJiraId): Action => Action::make("map_projects_{$projectJiraId}")
                                ->label(trans_choice('tempo::tempo.settings.action_map_projects', $projects->count(), ['jira' => $jiraIntegrations[$projectJiraId]]))
                                ->link()
                                ->url(ProjectMappingPage::getUrl(['record' => $projectJiraId, 'filters' => ['id' => ['ids' => $projects->pluck('id')->implode(',')]]])))->values(),
                        ]),
                    // Evaluated while rendering, so an import queued in this request already shows up
                    View::make('tempo::filament.pages.worklog-import-status')->viewData(fn (): array => [
                        'batch' => isset($integration->config['worklog_backfill_batch_id']) ? Bus::findBatch($integration->config['worklog_backfill_batch_id']) : null,
                    ]),
                ])
                ->footerActions([
                    Action::make('import_worklogs')
                        ->label(__('tempo::tempo.settings.action_import'))
                        // Evaluated while rendering, so the action enables as soon as the settings are saved
                        ->disabled(fn (): bool => ! WorklogImporter::isReady($this->getIntegration()))
                        ->modalDescription(__('tempo::tempo.settings.action_import_description'))
                        ->modalSubmitActionLabel(__('tempo::tempo.settings.action_import_start'))
                        ->schema([
                            DatePicker::make('from')
                                ->label(__('tempo::tempo.settings.field_import_from'))
                                ->maxDate($today)
                                ->required(),
                            DatePicker::make('until')
                                ->label(__('tempo::tempo.settings.field_import_until'))
                                ->default($today)
                                ->afterOrEqual('from')
                                ->maxDate($today)
                                ->required(),
                        ])
                        ->action(function (array $data) use ($integration, $timezone): void {
                            $from = CarbonImmutable::parse($data['from'], $timezone)->startOfDay();
                            $until = CarbonImmutable::parse($data['until'], $timezone)->startOfDay();
                            $jobs = [];

                            for ($start = $from; $start->lte($until); $start = $start->addWeek()) {
                                $jobs[] = new BackfillWorklogsJob($integration->id, $start, $start->addDays(6)->min($until));
                            }

                            // A failed week must not cancel the weeks after it
                            $batch = Bus::batch($jobs)->name("Tempo worklog import {$integration->id}")->allowFailures()->dispatch();
                            // The batch id is stored to show how many weeks are still queued
                            $integration->update(['config' => array_merge($integration->refresh()->config ?? [], ['worklog_backfill_batch_id' => $batch->id])]);

                            Notification::make()->title(trans_choice('tempo::tempo.settings.notification_import_queued', count($jobs)))->success()->send();
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
