<?php

namespace Timatic\Jira\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\User;
use Filament\Actions\Action;
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
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Url;
use Saloon\Exceptions\Request\RequestException;
use Timatic\Jira\Commands\SyncUsersCommand;
use Timatic\Jira\Models\UserMapping;

/**
 * Links Jira accounts to Timatic users, so integrations that read Jira or Tempo worklogs know whose time it is.
 */
class UserMappingPage extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'jira::filament.pages.jira-project-mapping-page';

    /** @var array<string, mixed> | null */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    public function getTitle(): string
    {
        return __('jira::jira.user_mapping.page_title', ['title' => $this->getRecord()->getAttribute('display_title')]);
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
            NavigationItem::make(__('jira::jira.user_mapping.nav_label'))
                ->url(UserMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === UserMappingPage::getUrl(['record' => $record])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(UserMapping::query()->where('integration_id', $this->getRecord()->getKey()))
            ->columns([
                TextColumn::make('display_name')->label(__('jira::jira.user_mapping.column_name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('jira::jira.user_mapping.column_email'))->placeholder(__('jira::jira.user_mapping.email_hidden'))->searchable(),
                TextColumn::make('user.full_name')->label(__('jira::jira.user_mapping.column_user'))->placeholder(__('jira::jira.common.not_linked')),
            ])
            ->filters([
                Filter::make('unmapped')
                    ->label(__('jira::jira.project_mapping.filter_unmapped'))
                    ->default()
                    ->query(fn (Builder $query) => $query->whereNull('user_id')),
                Filter::make('skipped_worklogs')
                    ->label(__('jira::jira.common.filter_skipped_worklogs'))
                    ->query(fn (Builder $query) => $query->scopes('skippedWorklogs')),
            ])
            ->actions([
                Action::make('link')
                    ->label(__('jira::jira.user_mapping.action_link'))
                    ->icon('heroicon-o-link')
                    ->iconButton()
                    ->fillForm(fn (UserMapping $record): array => ['user_id' => $record->user_id])
                    ->schema([
                        Select::make('user_id')
                            ->label(__('jira::jira.user_mapping.column_user'))
                            ->options(fn (): array => User::query()->orderBy('given_name')->get()->pluck('full_name', 'id')->all())
                            ->searchable()
                            ->nullable(),
                    ])
                    ->action(fn (UserMapping $record, array $data) => $record->update(['user_id' => $data['user_id'] ?: null])),
            ])
            ->heading(__('jira::jira.user_mapping.table_heading'))
            ->emptyStateHeading(__('jira::jira.user_mapping.empty_heading'))
            ->emptyStateDescription(__('jira::jira.user_mapping.empty_description'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('jira::jira.common.action_refresh'))
                ->action(function (): void {
                    try {
                        Artisan::call(SyncUsersCommand::class, ['integration' => $this->getRecord()->getKey()]);
                    } catch (RequestException) {
                        Notification::make()
                            ->title(__('jira::jira.common.connection_expired_title'))
                            ->body(__('jira::jira.common.connection_expired_body'))
                            ->warning()
                            ->send();
                    }

                    $this->resetTable();
                }),
        ];
    }
}
