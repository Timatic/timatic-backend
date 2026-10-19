<?php

namespace Timatic\GitHub\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use Timatic\GitHub\Models\CommitEmail;
use Timatic\GitHub\Models\UserLogin;

/**
 * Corrects or completes what the webhook learns about who is who on GitHub.
 */
class PeoplePage extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'github::filament.pages.github-people-page';

    public function getTitle(): string
    {
        return __('github::github.people.page_title', ['title' => $this->getIntegration()->display_title]);
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
            NavigationItem::make(__('github::github.settings.nav_label'))
                ->url(SettingsPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === SettingsPage::getUrl(['record' => $record])),
            NavigationItem::make(__('github::github.repository_mapping.nav_label'))
                ->url(RepositoryMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === RepositoryMappingPage::getUrl(['record' => $record])),
            NavigationItem::make(__('github::github.people.nav_label'))
                ->url(PeoplePage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === PeoplePage::getUrl(['record' => $record])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->orderBy('given_name'))
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('github::github.people.column_user'))
                    ->searchable(['given_name', 'family_name', 'email']),
                // ponytail: two queries per row, eager load via a subquery if the user list grows large
                TextColumn::make('github_login')
                    ->label(__('github::github.people.column_login'))
                    ->state(fn (User $record): ?string => UserLogin::query()->where('user_id', $record->id)->value('login'))
                    ->placeholder(__('github::github.common.not_linked')),
                TextColumn::make('github_emails')
                    ->label(__('github::github.people.column_emails'))
                    ->state(fn (User $record): array => CommitEmail::query()->where('user_id', $record->id)->orderBy('email')->pluck('email')->all())
                    ->badge()
                    ->placeholder(__('github::github.common.not_linked')),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('github::github.people.action_edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (User $record): array => [
                        'login' => UserLogin::query()->where('user_id', $record->id)->value('login'),
                        'emails' => CommitEmail::query()->where('user_id', $record->id)->orderBy('email')->pluck('email')->all(),
                    ])
                    ->schema(fn (User $record): array => [
                        TextInput::make('login')
                            ->label(__('github::github.people.field_login'))
                            ->rules([(new Unique('github_user_logins', 'login'))->whereNot('user_id', (int) $record->id)]),
                        TagsInput::make('emails')
                            ->label(__('github::github.people.field_emails'))
                            ->helperText(__('github::github.people.field_emails_helper'))
                            ->nestedRecursiveRules([
                                'email',
                                (new Unique('github_commit_emails', 'email'))->whereNot('user_id', (int) $record->id),
                            ]),
                    ])
                    ->action(function (User $record, array $data): void {
                        filled($data['login'])
                            ? UserLogin::query()->updateOrCreate(['user_id' => $record->id], ['login' => $data['login']])
                            : UserLogin::query()->where('user_id', $record->id)->delete();

                        /** @var array<int, string> $submittedEmails */
                        $submittedEmails = $data['emails'] ?? [];
                        $emails = collect($submittedEmails)->map(fn (string $email) => mb_strtolower($email))->unique();

                        CommitEmail::query()->where('user_id', $record->id)->whereNotIn('email', $emails)->delete();
                        $emails->each(fn (string $email) => CommitEmail::query()->firstOrCreate(['email' => $email], ['user_id' => $record->id]));
                    }),
            ])
            ->heading(__('github::github.people.table_heading'))
            ->description(__('github::github.people.table_description'));
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
