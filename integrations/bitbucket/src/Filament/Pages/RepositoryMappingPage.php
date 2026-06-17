<?php

namespace Timatic\Bitbucket\Filament\Pages;

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
use Timatic\Bitbucket\Connector;
use Timatic\Bitbucket\DataTransferObjects\BitbucketRepository;
use Timatic\Bitbucket\Models\RepositoryMapping;
use Timatic\Bitbucket\OAuthService;
use Timatic\Bitbucket\Requests\GetRepositoriesRequest;

class RepositoryMappingPage extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'bitbucket::filament.pages.bitbucket-repository-mapping-page';

    public function getTitle(): string
    {
        return __('bitbucket::bitbucket.repository_mapping.page_title', ['name' => $this->getIntegration()->name]);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if ($this->hasCredentials()) {
            $this->syncRepositoriesFromBitbucket();
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

    public function table(Table $table): Table
    {
        if (! $this->hasCredentials()) {
            return $table
                ->query(RepositoryMapping::query()->whereNull('id'))
                ->columns([])
                ->emptyStateHeading(__('bitbucket::bitbucket.repository_mapping.no_credentials_heading'))
                ->emptyStateDescription(__('bitbucket::bitbucket.repository_mapping.no_credentials_description'))
                ->emptyStateIcon('heroicon-o-cog-6-tooth');
        }

        return $table
            ->query(RepositoryMapping::query()->where('integration_id', $this->getRecord()->getKey()))
            ->columns([
                TextColumn::make('repository_slug')->label(__('bitbucket::bitbucket.repository_mapping.column_repository'))->searchable()->sortable(),
                TextColumn::make('repository_name')
                    ->label(__('bitbucket::bitbucket.repository_mapping.column_name'))
                    ->color(fn (RepositoryMapping $record) => $record->is_archived ? 'gray' : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')->label(__('bitbucket::bitbucket.common.column_customer'))->placeholder(__('bitbucket::bitbucket.common.not_linked'))->sortable(),
                TextColumn::make('budget_id')
                    ->label(__('bitbucket::bitbucket.common.column_budget'))
                    ->placeholder(__('bitbucket::bitbucket.common.not_linked'))
                    ->formatStateUsing(fn ($state, $record) => $record->budget?->getTitle()),
            ])
            ->filters([
                Filter::make('unmapped')
                    ->label(__('bitbucket::bitbucket.repository_mapping.filter_unmapped'))
                    ->default()
                    ->query(fn (Builder $query) => $query->whereNull('customer_id')),
                TernaryFilter::make('is_archived')
                    ->label(__('bitbucket::bitbucket.repository_mapping.filter_archived'))
                    ->default(false),
            ])
            ->actions([
                Action::make('archive')
                    ->label(__('bitbucket::bitbucket.repository_mapping.action_archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->visible(fn (RepositoryMapping $record) => ! $record->is_archived)
                    ->action(fn (RepositoryMapping $record) => $record->update(['is_archived' => true])),
                Action::make('restore')
                    ->label(__('bitbucket::bitbucket.repository_mapping.action_restore'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->iconButton()
                    ->visible(fn (RepositoryMapping $record) => $record->is_archived)
                    ->action(fn (RepositoryMapping $record) => $record->update(['is_archived' => false])),
            ])
            ->bulkActions([
                BulkAction::make('assign')
                    ->label(__('bitbucket::bitbucket.common.assign_action'))
                    ->form([
                        Select::make('customer_id')
                            ->label(__('bitbucket::bitbucket.common.customer_label'))
                            ->options(Customer::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->placeholder(__('bitbucket::bitbucket.common.no_customer'))
                            ->live(),
                        Select::make('budget_id')
                            ->label(__('bitbucket::bitbucket.common.budget_label'))
                            ->options(fn ($get) => Budget::query()
                                ->when($get('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
                                ->get()
                                ->mapWithKeys(fn (Budget $budget) => [$budget->id => $budget->getTitle()])
                            )
                            ->searchable()
                            ->nullable()
                            ->placeholder(__('bitbucket::bitbucket.common.no_budget')),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update([
                            'customer_id' => $data['customer_id'] ?: null,
                            'budget_id' => $data['budget_id'] ?: null,
                        ]);
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('archive')
                    ->label(__('bitbucket::bitbucket.repository_mapping.action_archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['is_archived' => true]))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->heading(__('bitbucket::bitbucket.repository_mapping.table_heading'))
            ->emptyStateHeading(__('bitbucket::bitbucket.repository_mapping.empty_heading'))
            ->emptyStateDescription(__('bitbucket::bitbucket.repository_mapping.empty_description'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('bitbucket::bitbucket.common.action_refresh'))
                ->action(function (): void {
                    $this->syncRepositoriesFromBitbucket();
                    $this->resetTable();
                }),
        ];
    }

    private function hasCredentials(): bool
    {
        $config = $this->getIntegration()->config ?? [];

        return filled($config['access_token'] ?? null)
            && filled($config['workspace'] ?? null);
    }

    private function syncRepositoriesFromBitbucket(): void
    {
        try {
            $integration = app(OAuthService::class)->refreshIfExpired($this->getIntegration());
            $config = $integration->config ?? [];
            $response = (new Connector($config))->send(new GetRepositoriesRequest($config['workspace']));

            if (in_array($response->status(), [401, 403])) {
                $this->notifyConnectionExpired();

                return;
            }

            /** @var array<int, BitbucketRepository> $repos */
            $repos = $response->dto() ?? [];

            foreach ($repos as $repo) {
                if ($repo->slug === '') {
                    continue;
                }

                RepositoryMapping::updateOrCreate(
                    [
                        'integration_id' => $integration->id,
                        'workspace_slug' => $config['workspace'],
                        'repository_slug' => $repo->slug,
                    ],
                    ['repository_name' => $repo->name !== '' ? $repo->name : $repo->slug]
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
            ->title(__('bitbucket::bitbucket.common.connection_expired_title'))
            ->body(__('bitbucket::bitbucket.common.connection_expired_body'))
            ->warning()
            ->send();
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
