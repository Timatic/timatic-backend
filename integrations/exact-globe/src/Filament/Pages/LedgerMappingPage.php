<?php

namespace Timatic\ExactGlobe\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\BudgetType;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * @property Schema $form
 */
class LedgerMappingPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'exact-globe::filament.pages.ledger-mapping-page';

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
            NavigationItem::make('Ledger mapping')
                ->url(LedgerMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === LedgerMappingPage::getUrl(['record' => $record])),
        ];
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $config = $this->getIntegration()->config ?? [];
        $ledgerMapping = $config['ledger_mapping'] ?? [];

        $this->form->fill([
            'name' => $this->getIntegration()->name,
            'ledger_mapping' => $ledgerMapping,
            'enabled' => array_fill_keys(array_keys($ledgerMapping), true),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Integration name')
                ->required(),

            ...$this->budgetTypeSections(),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label('Delete')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->getIntegration()->delete();
                    $this->redirect(IntegrationResource::getUrl('index'));
                }),

            Action::make('save')
                ->label('Save')
                ->action(function (): void {
                    $data = $this->form->getState();

                    $config = array_merge($this->getIntegration()->config ?? [], [
                        'ledger_mapping' => $this->completedMappingRows(
                            $data['ledger_mapping'] ?? [],
                            $data['enabled'] ?? [],
                        ),
                    ]);

                    $this->getIntegration()->update([
                        'name' => $data['name'],
                        'config' => $config,
                    ]);

                    Notification::make()->title('Ledger mapping saved.')->success()->send();
                }),
        ];
    }

    /**
     * @return list<Section>
     */
    private function budgetTypeSections(): array
    {
        return array_values(BudgetType::query()
            ->where('is_archived', false)
            ->orderBy('title')
            ->get()
            ->map(fn (BudgetType $budgetType): Section => Section::make($budgetType->title)
                ->description('Enable to map ledger accounts for '.$budgetType->title.' budgets. Disabled budget types are excluded from the export.')
                ->columns(2)
                ->schema([
                    Toggle::make("enabled.{$budgetType->id}")
                        ->label('Include in export')
                        ->live()
                        ->columnSpanFull(),

                    TextInput::make("ledger_mapping.{$budgetType->id}.usage_credit")
                        ->label('Verbruik credit ledger')
                        ->numeric()
                        ->visible(fn (Get $get): bool => (bool) $get("enabled.{$budgetType->id}")),
                    TextInput::make("ledger_mapping.{$budgetType->id}.usage_debit")
                        ->label('Verbruik debit ledger')
                        ->numeric()
                        ->visible(fn (Get $get): bool => (bool) $get("enabled.{$budgetType->id}")),
                    TextInput::make("ledger_mapping.{$budgetType->id}.release_credit")
                        ->label('Vrijval credit ledger')
                        ->numeric()
                        ->visible(fn (Get $get): bool => (bool) $get("enabled.{$budgetType->id}")),
                    TextInput::make("ledger_mapping.{$budgetType->id}.release_debit")
                        ->label('Vrijval debit ledger')
                        ->numeric()
                        ->visible(fn (Get $get): bool => (bool) $get("enabled.{$budgetType->id}")),
                ]))
            ->all());
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $enabled
     * @return array<string, array<string, mixed>>
     */
    private function completedMappingRows(array $rows, array $enabled): array
    {
        return array_filter(
            $rows,
            fn (array $row, string $budgetTypeId): bool => ($enabled[$budgetTypeId] ?? false)
                && filled($row['usage_credit'] ?? null)
                && filled($row['usage_debit'] ?? null)
                && filled($row['release_credit'] ?? null)
                && filled($row['release_debit'] ?? null),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
