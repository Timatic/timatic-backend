<?php

declare(strict_types=1);

namespace Timatic\GoogleCalendar\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Integrations\IntegrationTypeRegistry;
use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Actions\Action as TableAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
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
use Timatic\GoogleCalendar\Models\GoogleCalendarConnection;
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
        return $this->getIntegration()->display_title;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill(['title' => $this->getIntegration()->title]);
    }

    public function form(Schema $form): Schema
    {
        if (! $this->isConfigured()) {
            return $form->schema([
                Callout::make(__('google_calendar::google_calendar.settings.callout_not_configured_title'))
                    ->danger()
                    ->description(__('google_calendar::google_calendar.settings.callout_not_configured_description')),
            ])->statePath('data');
        }

        return $form->schema([
            TextInput::make('title')
                ->label(__('google_calendar::google_calendar.settings.field_title'))
                ->autocomplete(false)
                ->helperText(__('integrations.title_helper')),
            Callout::make(__('google_calendar::google_calendar.settings.callout_connect_title'))
                ->info()
                ->description(__('google_calendar::google_calendar.settings.callout_connect_description')),
        ])->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(GoogleCalendarConnection::query()->with('user'))
            ->columns([
                TextColumn::make('user.full_name')
                    ->label(__('google_calendar::google_calendar.settings.column_user'))
                    ->searchable(['given_name', 'family_name']),
                TextColumn::make('user.email')
                    ->label(__('google_calendar::google_calendar.settings.column_email')),
                TextColumn::make('created_at')
                    ->label(__('google_calendar::google_calendar.settings.column_connected_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                TableAction::make('disconnect')
                    ->label(__('google_calendar::google_calendar.settings.action_disconnect'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (GoogleCalendarConnection $record) => app(OAuthService::class)->disconnect($record)),
            ])
            ->emptyStateHeading(__('google_calendar::google_calendar.settings.empty_heading'))
            ->emptyStateDescription(__('google_calendar::google_calendar.settings.empty_description'));
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                DeleteAction::make()
                    ->successRedirectUrl(IntegrationResource::getUrl('index')),
            ]),

            Action::make('save')
                ->label(__('google_calendar::google_calendar.settings.action_save'))
                ->action(function (): void {
                    $data = $this->form->getState();
                    $this->getIntegration()->update(['title' => $data['title']]);
                    Notification::make()->title(__('google_calendar::google_calendar.settings.notification_title_updated'))->success()->send();
                }),
        ];
    }

    private function isConfigured(): bool
    {
        return app(IntegrationTypeRegistry::class)->missingConfig('google_calendar') === [];
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
