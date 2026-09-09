<?php

namespace Timatic\GitHub\Filament\Pages;

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
use Timatic\GitHub\Connector;
use Timatic\GitHub\DataTransferObjects\GitHubRepository;
use Timatic\GitHub\DataTransferObjects\GitHubRepositoryPage;
use Timatic\GitHub\Exceptions\GitHubException;
use Timatic\GitHub\InstallationService;
use Timatic\GitHub\Models\RepositoryMapping;
use Timatic\GitHub\Requests\GetInstallationRepositoriesRequest;

class RepositoryMappingPage extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'github::filament.pages.github-repository-mapping-page';

    public function getTitle(): string
    {
        return __('github::github.repository_mapping.page_title', ['name' => $this->getIntegration()->name]);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if ($this->hasInstallations()) {
            $this->syncRepositoriesFromGitHub();
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

    public function table(Table $table): Table
    {
        if (! $this->hasInstallations()) {
            return $table
                ->query(RepositoryMapping::query()->whereNull('id'))
                ->columns([])
                ->emptyStateHeading(__('github::github.repository_mapping.no_credentials_heading'))
                ->emptyStateDescription(__('github::github.repository_mapping.no_credentials_description'))
                ->emptyStateIcon('heroicon-o-cog-6-tooth');
        }

        return $table
            ->query(RepositoryMapping::query()->where('integration_id', $this->getRecord()->getKey()))
            ->columns([
                TextColumn::make('repository_full_name')
                    ->label(__('github::github.repository_mapping.column_repository'))
                    ->color(fn (RepositoryMapping $record) => $record->is_archived ? 'gray' : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label(__('github::github.common.column_customer'))
                    ->placeholder(__('github::github.common.not_linked'))
                    ->sortable(),
                TextColumn::make('budget_id')
                    ->label(__('github::github.common.column_budget'))
                    ->placeholder(__('github::github.common.not_linked'))
                    ->formatStateUsing(fn ($state, RepositoryMapping $record) => $record->budget?->getTitle()),
            ])
            ->filters([
                Filter::make('unmapped')
                    ->label(__('github::github.repository_mapping.filter_unmapped'))
                    ->default()
                    ->query(fn (Builder $query) => $query->whereNull('customer_id')),
                TernaryFilter::make('is_archived')
                    ->label(__('github::github.repository_mapping.filter_archived'))
                    ->default(false),
            ])
            ->actions([
                Action::make('archive')
                    ->label(__('github::github.repository_mapping.action_archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn (RepositoryMapping $record) => ! $record->is_archived)
                    ->action(fn (RepositoryMapping $record) => $record->update(['is_archived' => true])),
                Action::make('restore')
                    ->label(__('github::github.repository_mapping.action_restore'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->iconButton()
                    ->visible(fn (RepositoryMapping $record) => $record->is_archived)
                    ->action(fn (RepositoryMapping $record) => $record->update(['is_archived' => false])),
            ])
            ->bulkActions([
                BulkAction::make('assign')
                    ->label(__('github::github.common.assign_action'))
                    ->form([
                        Select::make('customer_id')
                            ->label(__('github::github.common.customer_label'))
                            ->options(Customer::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->placeholder(__('github::github.common.no_customer'))
                            ->live(),
                        Select::make('budget_id')
                            ->label(__('github::github.common.budget_label'))
                            ->options(fn ($get) => Budget::query()
                                ->when($get('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
                                ->get()
                                ->mapWithKeys(fn (Budget $budget) => [$budget->id => $budget->getTitle()])
                            )
                            ->searchable()
                            ->nullable()
                            ->placeholder(__('github::github.common.no_budget')),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update([
                            'customer_id' => $data['customer_id'] ?: null,
                            'budget_id' => $data['budget_id'] ?: null,
                        ]);
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('archive')
                    ->label(__('github::github.repository_mapping.action_archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['is_archived' => true]))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->heading(__('github::github.repository_mapping.table_heading'))
            ->emptyStateHeading(__('github::github.repository_mapping.empty_heading'))
            ->emptyStateDescription(__('github::github.repository_mapping.empty_description'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('github::github.common.action_refresh'))
                ->action(function (): void {
                    $this->syncRepositoriesFromGitHub();
                    $this->resetTable();
                }),
        ];
    }

    private function hasInstallations(): bool
    {
        return $this->installationIds() !== [];
    }

    /** @return array<int, int> */
    private function installationIds(): array
    {
        return array_keys(app(InstallationService::class)->stored($this->getIntegration()->config ?? []));
    }

    private function syncRepositoriesFromGitHub(): void
    {
        $integration = $this->getIntegration();
        $syncedFullNames = [];
        $syncedInstallationIds = [];

        foreach ($this->installationIds() as $installationId) {
            try {
                $repositories = $this->fetchRepositories($installationId);
            } catch (GitHubException $e) {
                $this->notifyConnectionUnavailable();

                continue;
            }

            foreach ($repositories as $repository) {
                if ($repository->fullName === '') {
                    continue;
                }

                RepositoryMapping::updateOrCreate(
                    [
                        'integration_id' => $integration->id,
                        'repository_full_name' => $repository->fullName,
                    ],
                    [
                        'installation_id' => $installationId,
                        'owner_login' => $repository->ownerLogin,
                        'repository_name' => $repository->name,
                        'is_archived' => $repository->isArchived,
                    ]
                );

                $syncedFullNames[] = $repository->fullName;
            }

            $syncedInstallationIds[] = $installationId;
        }

        $this->archiveRepositoriesNoLongerAccessible($integration, $syncedInstallationIds, $syncedFullNames);
    }

    /**
     * @return array<int, GitHubRepository>
     *
     * @throws GitHubException
     */
    private function fetchRepositories(int $installationId): array
    {
        $connector = Connector::forInstallation($installationId);
        $repositories = [];
        $page = 1;

        do {
            $response = $connector->send(new GetInstallationRepositoriesRequest($page));

            if ($response->failed()) {
                throw new GitHubException('Fetching installation repositories failed with status '.$response->status().'.');
            }

            /** @var GitHubRepositoryPage $repositoryPage */
            $repositoryPage = $response->dto();
            $repositories = [...$repositories, ...$repositoryPage->repositories];
            $page++;
        } while ($repositoryPage->repositories !== [] && count($repositories) < $repositoryPage->totalCount);

        return $repositories;
    }

    /**
     * Only installations that answered are swept, so a failing installation does not
     * archive the repositories it still owns.
     *
     * @param  array<int, int>  $syncedInstallationIds
     * @param  array<int, string>  $syncedFullNames
     */
    private function archiveRepositoriesNoLongerAccessible(Integration $integration, array $syncedInstallationIds, array $syncedFullNames): void
    {
        RepositoryMapping::query()
            ->where('integration_id', $integration->id)
            ->whereIn('installation_id', $syncedInstallationIds)
            ->whereNotIn('repository_full_name', $syncedFullNames)
            ->update(['is_archived' => true]);
    }

    private function notifyConnectionUnavailable(): void
    {
        Notification::make()
            ->title(__('github::github.common.connection_expired_title'))
            ->body(__('github::github.common.connection_expired_body'))
            ->warning()
            ->send();
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
