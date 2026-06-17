<?php

namespace Timatic\Jira\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Budget;
use App\Models\Customer;
use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Saloon\Exceptions\Request\RequestException;
use Timatic\Jira\Connector;
use Timatic\Jira\DataTransferObjects\JiraProject;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\OAuthService;
use Timatic\Jira\Requests\GetProjectsRequest;

class ProjectMappingPage extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'jira::filament.pages.jira-project-mapping-page';

    public function getTitle(): string
    {
        return __('jira::jira.project_mapping.page_title', ['name' => $this->getIntegration()->name]);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if ($this->hasCredentials()) {
            $this->syncProjectsFromJira();
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

    public function table(Table $table): Table
    {
        if (! $this->hasCredentials()) {
            return $table
                ->query(ProjectMapping::query()->whereNull('id'))
                ->columns([])
                ->emptyStateHeading(__('jira::jira.project_mapping.no_credentials_heading'))
                ->emptyStateDescription(__('jira::jira.project_mapping.no_credentials_description'))
                ->emptyStateIcon('heroicon-o-cog-6-tooth');
        }

        return $table
            ->query(ProjectMapping::query()->where('integration_id', $this->getRecord()->getKey()))
            ->columns([
                TextColumn::make('project_key')->label(__('jira::jira.project_mapping.column_project_key'))->searchable()->sortable(),
                TextColumn::make('project_name')
                    ->label(__('jira::jira.project_mapping.column_name'))
                    ->color(fn (ProjectMapping $record) => $record->is_archived ? 'gray' : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')->label(__('jira::jira.common.column_customer'))->placeholder(__('jira::jira.common.not_linked'))->sortable(),
                TextColumn::make('budget_id')
                    ->label(__('jira::jira.common.column_budget'))
                    ->placeholder(__('jira::jira.common.not_linked'))
                    ->formatStateUsing(fn ($state, $record) => $record->budget?->getTitle()),
            ])
            ->filters([
                Filter::make('unmapped')
                    ->label(__('jira::jira.project_mapping.filter_unmapped'))
                    ->default()
                    ->query(fn (Builder $query) => $query->whereNull('customer_id')),
                TernaryFilter::make('is_archived')
                    ->label(__('jira::jira.project_mapping.filter_archived'))
                    ->default(false),
            ])
            ->actions([
                Action::make('archive')
                    ->label(__('jira::jira.project_mapping.action_archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn (ProjectMapping $record) => ! $record->is_archived)
                    ->action(fn (ProjectMapping $record) => $record->update(['is_archived' => true])),
                Action::make('restore')
                    ->label(__('jira::jira.project_mapping.action_restore'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->iconButton()
                    ->visible(fn (ProjectMapping $record) => $record->is_archived)
                    ->action(fn (ProjectMapping $record) => $record->update(['is_archived' => false])),
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
                BulkAction::make('archive')
                    ->label(__('jira::jira.project_mapping.action_archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['is_archived' => true]))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->heading(__('jira::jira.project_mapping.table_heading'))
            ->emptyStateHeading(__('jira::jira.project_mapping.empty_heading'))
            ->emptyStateDescription(__('jira::jira.project_mapping.empty_description'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('jira::jira.common.action_refresh'))
                ->action(function (): void {
                    $this->syncProjectsFromJira();
                    $this->resetTable();
                }),
        ];
    }

    private function hasCredentials(): bool
    {
        $config = $this->getIntegration()->config ?? [];

        return filled($config['access_token'] ?? null)
            && filled($config['cloud_id'] ?? null);
    }

    private function syncProjectsFromJira(): void
    {
        try {
            $integration = app(OAuthService::class)->refreshIfExpired($this->getIntegration());
            $response = (new Connector($integration->config ?? []))->send(new GetProjectsRequest);

            if (in_array($response->status(), [401, 403])) {
                $this->notifyConnectionExpired();

                return;
            }

            /** @var array<int, JiraProject> $projects */
            $projects = $response->dto() ?? [];

            foreach ($projects as $project) {
                if ($project->key === '') {
                    continue;
                }

                ProjectMapping::updateOrCreate(
                    [
                        'integration_id' => $integration->id,
                        'project_key' => $project->key,
                    ],
                    ['project_name' => $project->name !== '' ? $project->name : $project->key]
                );
            }
        } catch (RequestException $e) {
            if (in_array($e->getResponse()->status(), [401, 403])) {
                $this->notifyConnectionExpired();
            }
        }
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
