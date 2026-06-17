<?php

namespace Timatic\Rework\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Timatic\Rework\Connector;
use Timatic\Rework\DataTransferObjects\ReworkUser;
use Timatic\Rework\Requests\GetUsersRequest;

/**
 * @property Table $table
 */
class UserMappingPage extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'rework::filament.pages.user-mapping-page';

    /** @var array<int, array{id: string, rework_email: string|null, rework_name: string|null, timatic_user_name: string|null, timatic_user_email: string|null, is_mapped: bool}> */
    public array $users = [];

    public function getTitle(): string
    {
        return $this->getIntegration()->name.' — Gebruikers';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if ($this->isConnected()) {
            $this->loadUsers();
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
            NavigationItem::make('Instellingen')
                ->url(SettingsPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === SettingsPage::getUrl(['record' => $record])),
            NavigationItem::make('Gebruikers')
                ->url(UserMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === UserMappingPage::getUrl(['record' => $record])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->users)
            ->columns([
                IconColumn::make('is_mapped')
                    ->label('Gekoppeld')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('rework_email')
                    ->label('E-mail Rework'),
                TextColumn::make('rework_name')
                    ->label('Naam Rework'),
                TextColumn::make('timatic_user_name')
                    ->label('Timatic gebruiker')
                    ->placeholder('—'),
                TextColumn::make('timatic_user_email')
                    ->label('E-mail Timatic')
                    ->placeholder('—'),
            ])
            ->heading('Gebruikers')
            ->emptyStateHeading('Geen gebruikers gevonden')
            ->emptyStateDescription('Klik op "Vernieuwen" om gebruikers op te halen uit Rework.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Vernieuwen')
                ->action(function (): void {
                    Cache::forget($this->reworkUsersCacheKey());
                    $this->loadUsers();
                    $this->resetTable();
                }),
        ];
    }

    private function loadUsers(): void
    {
        /** @var array<string, array{id: int, email: string, name: string}> $reworkUsers */
        $reworkUsers = Cache::remember(
            $this->reworkUsersCacheKey(),
            now()->addHour(),
            fn () => $this->fetchReworkUsers(),
        );

        /** @var Collection<string, User> $allUsersByEmail */
        $allUsersByEmail = User::all()->keyBy(fn (User $user) => strtolower((string) $user->email));

        $reworkEmailsLower = array_column($reworkUsers, 'email');

        $rows = collect($reworkUsers)
            ->map(function (array $reworkUser) use ($allUsersByEmail): array {
                $user = $allUsersByEmail->get($reworkUser['email']);

                return [
                    'id' => 'rework_'.$reworkUser['id'],
                    'rework_email' => $reworkUser['email'],
                    'rework_name' => $reworkUser['name'],
                    'timatic_user_name' => $user?->full_name,
                    'timatic_user_email' => $user?->email,
                    'is_mapped' => $user !== null,
                ];
            });

        $unmatchedTimaticUsers = $allUsersByEmail
            ->filter(fn (User $user) => ! in_array(strtolower((string) $user->email), $reworkEmailsLower))
            ->map(fn (User $user): array => [
                'id' => 'timatic_'.$user->id,
                'rework_email' => null,
                'rework_name' => null,
                'timatic_user_name' => $user->full_name,
                'timatic_user_email' => $user->email,
                'is_mapped' => false,
            ]);

        $this->users = $rows->merge($unmatchedTimaticUsers)->values()->all();
    }

    /** @return array<string, array{id: int, email: string, name: string}> */
    private function fetchReworkUsers(): array
    {
        $connector = app(Connector::class);

        /** @var Collection<int, ReworkUser> $reworkUsers */
        $reworkUsers = $connector->paginate(new GetUsersRequest)
            ->collect()
            ->values();

        return $reworkUsers
            ->mapWithKeys(fn (ReworkUser $user): array => [
                $user->email => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ],
            ])
            ->all();
    }

    private function reworkUsersCacheKey(): string
    {
        return 'rework_users_'.$this->getRecord()->getKey();
    }

    private function isConnected(): bool
    {
        $config = $this->getIntegration()->config ?? [];

        return filled($config['api_key'] ?? null) && filled($config['company_id'] ?? null);
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
