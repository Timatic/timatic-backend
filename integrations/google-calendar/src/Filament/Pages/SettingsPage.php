<?php

namespace Timatic\GoogleCalendar\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Action as TableAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Timatic\GoogleCalendar\OAuthService;

/**
 * @property Schema $form
 */
class SettingsPage extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'google_calendar::filament.pages.settings-page';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return $this->getIntegration()->name;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill(['name' => $this->getIntegration()->name]);
    }

    public function form(Schema $form): Schema
    {
        if (! $this->isConfigured()) {
            return $form->schema([
                Callout::make('Google Calendar credentials are not configured.')
                    ->danger()
                    ->description('Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to your .env file.'),
            ])->statePath('data');
        }

        return $form->schema([
            TextInput::make('name')
                ->label('Integration name')
                ->required(),
            Callout::make('Users connect their Google Calendar by logging in with Google.')
                ->info()
                ->description('Each user\'s calendar access is obtained automatically during Google login. The table below shows all currently connected users.'),
        ])->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()->whereNotNull('oauth_refresh_token')
            )
            ->columns([
                TextColumn::make('full_name')
                    ->label('User')
                    ->searchable(['given_name', 'family_name']),
                TextColumn::make('email')
                    ->label('Email'),
                TextColumn::make('updated_at')
                    ->label('Connected at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                TableAction::make('disconnect')
                    ->label('Disconnect')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => app(OAuthService::class)->disconnect($record)),
            ])
            ->emptyStateHeading('No users connected yet')
            ->emptyStateDescription('Users will appear here after they log in with Google and grant calendar access.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action(function (): void {
                    $data = $this->form->getState();
                    $this->getIntegration()->update(['name' => $data['name']]);
                    Notification::make()->title('Name updated.')->success()->send();
                }),
        ];
    }

    private function isConfigured(): bool
    {
        return filled(config('google_calendar.client_id')) && filled(config('google_calendar.client_secret'));
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
