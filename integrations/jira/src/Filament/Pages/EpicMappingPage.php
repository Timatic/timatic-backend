<?php

namespace Timatic\Jira\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Budget;
use App\Models\Customer;
use App\Models\Integration;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Url;
use Saloon\Exceptions\Request\RequestException;
use Timatic\Jira\Connector;
use Timatic\Jira\DataTransferObjects\JiraIssue;
use Timatic\Jira\Models\EpicMapping;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\OAuthService;
use Timatic\Jira\Requests\GetIssuesRequest;

/**
 * Links the epics of the projects added here to a customer and budget, for a project
 * whose epics belong to different customers or to different budgets of one customer.
 */
class EpicMappingPage extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'jira::filament.pages.jira-project-mapping-page';

    /** @var array<string, mixed> | null In the URL so another page can link to rows by id: ?filters[id][ids]=1,2,4 */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    public function getTitle(): string
    {
        return __('jira::jira.epic_mapping.page_title', ['title' => $this->getIntegration()->display_title]);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
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

    public function table(Table $table): Table
    {
        return $table
            // A closure, so adding or removing a project shows in the same request
            ->query(fn (): Builder => EpicMapping::query()
                ->where('integration_id', $this->getRecord()->getKey())
                ->where(function (Builder $query): void {
                    // Matches nothing until a project is added
                    $query->whereRaw('1 = 0');

                    foreach ($this->projectsMappedPerEpic()->pluck('project_key') as $projectKey) {
                        $query->orWhere('epic_key', 'like', addcslashes($projectKey, '_%').'-%');
                    }
                }))
            ->columns([
                TextColumn::make('epic_key')->label(__('jira::jira.epic_mapping.column_epic_key'))->searchable()->sortable(),
                TextColumn::make('epic_name')->label(__('jira::jira.epic_mapping.column_name'))->searchable()->sortable(),
                TextColumn::make('customer.name')->label(__('jira::jira.common.column_customer'))->placeholder(__('jira::jira.common.not_linked'))->sortable(),
                TextColumn::make('budget_id')
                    ->label(__('jira::jira.common.column_budget'))
                    ->placeholder(__('jira::jira.common.not_linked'))
                    ->formatStateUsing(fn ($state, EpicMapping $record) => $record->budget?->getTitle()),
            ])
            ->filters([
                Filter::make('without_customer')
                    ->label(__('jira::jira.common.filter_without_customer'))
                    ->query(fn (Builder $query) => $query->whereNull('customer_id')),
                Filter::make('without_budget')
                    ->label(__('jira::jira.common.filter_without_budget'))
                    ->default()
                    ->query(fn (Builder $query) => $query->whereNull('budget_id')),
                Filter::make('id')
                    ->schema([Hidden::make('ids')])
                    ->query(fn (Builder $query, array $data) => $query->when(filled($data['ids'] ?? null), fn (Builder $query) => $query->whereKey(explode(',', (string) $data['ids']))))
                    ->indicateUsing(fn (array $data): ?string => filled($data['ids'] ?? null) ? trans_choice('jira::jira.common.filter_ids_indicator', count(explode(',', (string) $data['ids']))) : null),
            ])
            ->bulkActions([
                BulkAction::make('assign')
                    ->label(__('jira::jira.common.assign_action'))
                    ->form([
                        Select::make('customer_id')
                            ->label(__('jira::jira.common.customer_label'))
                            ->options(Customer::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->placeholder(__('jira::jira.common.no_customer'))
                            ->live(),
                        Select::make('budget_id')
                            ->label(__('jira::jira.common.budget_label'))
                            ->options(fn ($get) => Budget::query()
                                ->when($get('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
                                ->get()
                                ->mapWithKeys(fn (Budget $budget) => [$budget->id => $budget->getTitle()])
                            )
                            ->searchable()
                            ->nullable()
                            ->placeholder(__('jira::jira.common.no_budget')),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update([
                            'customer_id' => $data['customer_id'] ?: null,
                            'budget_id' => $data['budget_id'] ?: null,
                        ]);
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->heading(__('jira::jira.epic_mapping.table_heading'))
            ->description(fn (): ?string => $this->lastSyncedDescription())
            ->emptyStateHeading(__('jira::jira.epic_mapping.empty_heading'))
            ->emptyStateDescription(__('jira::jira.epic_mapping.empty_description'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_project')
                ->label(__('jira::jira.epic_mapping.action_add_project'))
                ->schema([
                    Select::make('project_mapping_id')
                        ->label(__('jira::jira.epic_mapping.field_project'))
                        ->options(fn (): array => $this->projectOptions(isMappedPerEpic: false))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $project = ProjectMapping::active()->where('integration_id', $this->getRecord()->getKey())->whereKey($data['project_mapping_id'])->firstOrFail();
                    $project->update(['is_mapped_per_epic' => true]);
                    $this->syncEpicsFromJira(collect([$project->project_key]));
                    $this->resetTable();
                }),
            Action::make('remove_project')
                ->label(__('jira::jira.epic_mapping.action_remove_project'))
                ->color('gray')
                ->visible(fn (): bool => $this->projectsMappedPerEpic()->exists())
                ->modalDescription(__('jira::jira.epic_mapping.remove_project_description'))
                ->schema([
                    Select::make('project_mapping_id')
                        ->label(__('jira::jira.epic_mapping.field_project'))
                        ->options(fn (): array => $this->projectOptions(isMappedPerEpic: true))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->projectsMappedPerEpic()->whereKey($data['project_mapping_id'])->update(['is_mapped_per_epic' => false]);
                    $this->resetTable();
                }),
            Action::make('refresh')
                ->label(__('jira::jira.common.action_refresh'))
                ->action(function (): void {
                    $this->syncEpicsFromJira($this->projectsMappedPerEpic()->pluck('project_key'));
                    $this->resetTable();
                }),
        ];
    }

    /** @return Builder<ProjectMapping> */
    private function projectsMappedPerEpic(): Builder
    {
        return ProjectMapping::active()->where('integration_id', $this->getRecord()->getKey())->where('is_mapped_per_epic', true);
    }

    /** @return array<int, string> */
    private function projectOptions(bool $isMappedPerEpic): array
    {
        return ProjectMapping::active()
            ->where('integration_id', $this->getRecord()->getKey())
            ->where('is_mapped_per_epic', $isMappedPerEpic)
            ->orderBy('project_key')
            ->get()
            ->mapWithKeys(fn (ProjectMapping $project): array => [$project->id => "{$project->project_key} — {$project->project_name}"])
            ->all();
    }

    /** @param SupportCollection<int, string> $projectKeys */
    private function syncEpicsFromJira(SupportCollection $projectKeys): void
    {
        if ($projectKeys->isEmpty()) {
            return;
        }

        try {
            $integration = app(OAuthService::class)->refreshIfExpired($this->getIntegration());

            if (! Connector::hasCredentials($integration->config ?? [])) {
                $this->notifyConnectionExpired();

                return;
            }

            $epics = (new GetIssuesRequest('project in ("'.$projectKeys->implode('","').'") AND hierarchyLevel = 1'))
                ->paginate(new Connector($integration->config ?? []))
                ->items();

            /** @var JiraIssue $epic */
            foreach ($epics as $epic) {
                EpicMapping::query()->updateOrCreate(
                    ['integration_id' => $integration->id, 'epic_key' => $epic->key],
                    ['epic_name' => $epic->summary !== '' ? $epic->summary : $epic->key],
                );
            }

            $integration->refresh();
            $integration->update(['config' => [...$integration->config, 'epics_synced_at' => now()->timestamp]]);
        } catch (RequestException) {
            $this->notifyConnectionExpired();
        }
    }

    private function lastSyncedDescription(): ?string
    {
        $syncedAt = $this->getIntegration()->config['epics_synced_at'] ?? null;

        if ($syncedAt === null) {
            return null;
        }

        return __('jira::jira.common.last_synced', ['at' => CarbonImmutable::createFromTimestamp($syncedAt, config('timatic.preferred_timezone'))->format('d-m-Y H:i')]);
    }

    private function notifyConnectionExpired(): void
    {
        Notification::make()
            ->title(__('jira::jira.common.connection_expired_title'))
            ->body(__('jira::jira.common.connection_expired_body'))
            ->warning()
            ->send();
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
