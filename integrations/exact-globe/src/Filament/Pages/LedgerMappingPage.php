<?php

namespace Timatic\ExactGlobe\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\BudgetType;
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

        $this->form->fill([
            'name' => $this->getIntegration()->name,
            'ledger_mapping' => $config['ledger_mapping'] ?? [],
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
                        'ledger_mapping' => $this->completedMappingRows($data['ledger_mapping'] ?? []),
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
                ->description('Ledger accounts for '.$budgetType->title.' budgets. Leave empty to exclude this budget type from the export.')
                ->columns(2)
                ->schema([
                    TextInput::make("ledger_mapping.{$budgetType->id}.usage_credit")
                        ->label('Verbruik credit ledger')
                        ->numeric(),
                    TextInput::make("ledger_mapping.{$budgetType->id}.usage_debit")
                        ->label('Verbruik debit ledger')
                        ->numeric(),
                    TextInput::make("ledger_mapping.{$budgetType->id}.release_credit")
                        ->label('Vrijval credit ledger')
                        ->numeric(),
                    TextInput::make("ledger_mapping.{$budgetType->id}.release_debit")
                        ->label('Vrijval debit ledger')
                        ->numeric(),
                ]))
            ->all());
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function completedMappingRows(array $rows): array
    {
        return array_filter(
            $rows,
            fn (array $row): bool => filled($row['usage_credit'] ?? null)
                && filled($row['usage_debit'] ?? null)
                && filled($row['release_credit'] ?? null)
                && filled($row['release_debit'] ?? null),
        );
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
