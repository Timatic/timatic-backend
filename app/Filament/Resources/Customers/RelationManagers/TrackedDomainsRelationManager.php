<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\Budget;
use App\Models\Customer;
use App\Models\TrackedDomain;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrackedDomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'trackedDomains';

    protected static ?string $title = 'Tracked domains';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('domain')
                ->required()
                ->helperText('Paste a url if you like: the path moves to the field below. Subdomains are covered too, so acme.com also tracks app.acme.com.')
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    $set('domain', TrackedDomain::normalise((string) $state));

                    $path = TrackedDomain::normalisePath((string) $state);

                    if ($path !== '') {
                        $set('path', $path);
                    }
                })
                ->dehydrateStateUsing(fn (string $state): string => TrackedDomain::normalise($state)),
            TextInput::make('path')
                ->helperText('Optional. /projects/TIM only tracks that part of the domain, and wins over a mapping on the whole domain.')
                ->dehydrateStateUsing(fn (?string $state): string => TrackedDomain::normalisePath((string) $state)),
            Select::make('budget_id')
                ->label('Budget')
                ->helperText('Leave empty to book the time as paid per hour.')
                ->options(fn (): array => $this->budgetOptions())
                ->searchable(),
            Toggle::make('is_internal')->label('Internal'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            // Private mappings are somebody's local development setup. They belong to that engineer
            // and are managed from the extension, not from here.
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('user_id'))
            ->columns([
                TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->state(fn (TrackedDomain $record): string => $record->domain.$record->path),
                TextColumn::make('budget')
                    ->label('Budget')
                    ->state(fn (TrackedDomain $record): ?string => $record->budget?->getTitle()),
                IconColumn::make('is_internal')->label('Internal')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function budgetOptions(): array
    {
        /** @var Customer $customer */
        $customer = $this->getOwnerRecord();

        return Budget::query()
            ->where('customer_id', $customer->id)
            ->get()
            ->mapWithKeys(fn (Budget $budget): array => [$budget->id => $budget->getTitle()])
            ->all();
    }
}
