<?php

namespace Timatic\Nmbrs\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use App\Models\Overtime;
use Filament\Actions\Action;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Concerns\HasTabs;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Throwable;
use Timatic\Nmbrs\Actions\PushOvertimesAction;

/**
 * @property Table $table
 */
class OvertimeSyncPage extends Page implements HasTable
{
    use HasTabs;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = IntegrationResource::class;

    public function getTitle(): string
    {
        return $this->getIntegration()->name.' — Overwerk synchronisatie';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            EmbeddedTable::make(),
        ]);
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
            NavigationItem::make('Medewerkers')
                ->url(EmployeeMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === EmployeeMappingPage::getUrl(['record' => $record])),
            NavigationItem::make('Overwerk synchronisatie')
                ->url(OvertimeSyncPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === OvertimeSyncPage::getUrl(['record' => $record])),
        ];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Te synchroniseren')
                ->query(fn ($query) => $query->isExported(false)),
            'synced' => Tab::make('Gesynchroniseerd')
                ->query(fn ($query) => $query->isExported(true)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->modifyQueryWithActiveTab(
                Overtime::isApproved(true)->with('entry.user')
            ))
            ->columns([
                TextColumn::make('employee')
                    ->label('Medewerker')
                    ->state(fn (Overtime $record): string => $record->entry?->user->full_name ?? '—'),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->state(fn (Overtime $record): string => $record->entry?->user->email ?? '—'),
                TextColumn::make('started_at')
                    ->label('Datum')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('percentages')
                    ->label('Overwerk')
                    ->state(fn (Overtime $record): string => $this->formatPercentages($record)),
                TextColumn::make('exported_at')
                    ->label('Gesynchroniseerd op')
                    ->date('d-m-Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->heading('Overwerk synchronisatie')
            ->emptyStateHeading('Geen overwerk gevonden')
            ->defaultSort('started_at', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_now')
                ->label('Synchroniseer nu')
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        $result = app(PushOvertimesAction::class)->execute();
                    } catch (Throwable $e) {
                        Notification::make()->title('Synchronisatie mislukt')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    if ($result->exportedCount === 0 && ! $result->hasWarnings()) {
                        Notification::make()->title('Geen overwerk om te synchroniseren.')->info()->send();
                    } else {
                        if ($result->exportedCount > 0) {
                            Notification::make()->title($result->exportedCount.' overwerk(en) gesynchroniseerd.')->success()->send();
                        }

                        foreach ($result->warnings as $warning) {
                            Notification::make()->title($warning)->warning()->send();
                        }
                    }

                    $this->resetTable();
                }),
        ];
    }

    private function formatPercentages(Overtime $overtime): string
    {
        $percentages = $overtime->percentages;

        if ($percentages === null) {
            return '—';
        }

        $totals = [];

        foreach ((array) $percentages as $entry) {
            $percentage = (int) $entry->percentage;
            $totals[$percentage] = ($totals[$percentage] ?? 0) + (int) $entry->minutes;
        }

        $parts = [];

        foreach ($totals as $percentage => $minutes) {
            $parts[] = $percentage.'%: '.round($minutes / 60, 2).'u';
        }

        return implode(', ', $parts) ?: '—';
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
