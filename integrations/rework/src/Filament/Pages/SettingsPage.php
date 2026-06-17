<?php

namespace Timatic\Rework\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * @property Schema $form
 */
class SettingsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'rework::filament.pages.settings-page';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return $this->getIntegration()->name;
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

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $config = $this->getIntegration()->config ?? [];

        $this->form->fill([
            'name' => $this->getIntegration()->name,
            'api_key' => $config['api_key'] ?? null,
            'company_id' => $config['company_id'] ?? null,
            'sync_leave_enabled' => $config['sync_leave_enabled'] ?? true,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Naam integratie')
                ->required(),

            TextInput::make('api_key')
                ->label('API sleutel')
                ->password()
                ->revealable()
                ->required()
                ->helperText('Bearer token voor authenticatie met de Rework API.'),

            TextInput::make('company_id')
                ->label('Bedrijfs-ID')
                ->required()
                ->helperText('Te vinden in de Rework URL: https://app.rework.nl/{company_id}/...'),

            Section::make('Verlof synchronisatie')
                ->schema([
                    Toggle::make('sync_leave_enabled')
                        ->label('Verlof automatisch synchroniseren')
                        ->helperText('Dagelijks om 07:00 worden goedgekeurde verlofaanvragen als entries aangemaakt.')
                        ->default(true),
                ]),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Opslaan')
                ->action(function (): void {
                    $data = $this->form->getState();

                    $config = array_merge($this->getIntegration()->config ?? [], [
                        'api_key' => $data['api_key'],
                        'company_id' => $data['company_id'],
                        'sync_leave_enabled' => (bool) ($data['sync_leave_enabled'] ?? true),
                    ]);

                    $this->getIntegration()->update([
                        'name' => $data['name'],
                        'config' => $config,
                    ]);

                    Notification::make()->title('Instellingen opgeslagen.')->success()->send();
                }),
        ];
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
