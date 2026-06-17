<?php

namespace Timatic\Topdesk\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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

    protected string $view = 'topdesk::filament.pages.settings-page';

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
            NavigationItem::make('Settings')
                ->url(SettingsPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === SettingsPage::getUrl(['record' => $record])),
        ];
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $config = $this->getIntegration()->config ?? [];

        $this->form->fill([
            'name' => $this->getIntegration()->name,
            'base_url' => $config['base_url'] ?? null,
            'username' => $config['username'] ?? null,
            'api_token' => $config['api_token'] ?? null,
            'branch_match_field' => $config['branch_match_field'] ?? 'clientReferenceNumber',
            'ticket_key_pattern' => $config['ticket_key_pattern'] ?? '[A-Z]+\s?\d+',
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Integration name')
                ->required(),

            TextInput::make('base_url')
                ->label('TOPdesk URL')
                ->placeholder('https://company.topdesk.net')
                ->url()
                ->required(),

            TextInput::make('username')
                ->label('Username')
                ->required(),

            TextInput::make('api_token')
                ->label('Application password')
                ->password()
                ->revealable()
                ->required(),

            Section::make('Advanced')
                ->schema([
                    TextInput::make('branch_match_field')
                        ->label('Branch match field')
                        ->default('clientReferenceNumber')
                        ->helperText('TOPdesk branch field compared against the Timatic customer external ID.')
                        ->required(),

                    TextInput::make('ticket_key_pattern')
                        ->label('Ticket key pattern')
                        ->default('[A-Z]+\s?\d+')
                        ->helperText('Regex pattern used to detect incident numbers in text (e.g. IMX\d+ or I\s\d+).')
                        ->required(),
                ]),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action(function (): void {
                    $data = $this->form->getState();

                    $config = array_merge($this->getIntegration()->config ?? [], [
                        'base_url' => $data['base_url'],
                        'username' => $data['username'],
                        'api_token' => $data['api_token'],
                        'branch_match_field' => $data['branch_match_field'],
                        'ticket_key_pattern' => $data['ticket_key_pattern'],
                    ]);

                    $this->getIntegration()->update([
                        'name' => $data['name'],
                        'config' => $config,
                    ]);

                    Notification::make()->title('Settings saved.')->success()->send();
                }),
        ];
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
