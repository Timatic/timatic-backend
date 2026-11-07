<?php

namespace Timatic\ExactGlobe\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;

/**
 * @property Schema $form
 */
class SettingsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'exact-globe::filament.pages.settings-page';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return $this->getIntegration()->display_title;
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
            NavigationItem::make('Ledger mapping')
                ->url(LedgerMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === LedgerMappingPage::getUrl(['record' => $record])),
        ];
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->form->fill(['title' => $this->getIntegration()->title]);
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('title')
                ->label('Integration title')
                ->autocomplete(false)
                ->helperText(__('integrations.title_helper')),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                DeleteAction::make()
                    ->successRedirectUrl(IntegrationResource::getUrl('index')),
            ]),

            Action::make('save')
                ->label('Save')
                ->action(function (): void {
                    $data = $this->form->getState();
                    $this->getIntegration()->update(['title' => $data['title']]);

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
