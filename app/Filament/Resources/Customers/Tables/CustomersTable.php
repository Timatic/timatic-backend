<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('external_id')->label('External ID')->searchable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('hourly_rate')->money('EUR'),
                TextColumn::make('accountManager.email')->label('Account manager'),
                // Shared mappings only, the same set the relation manager shows. A customer whose
                // domains are only mapped privately still reads as untracked here, which is the
                // point: nobody on the team is tracking it.
                TextColumn::make('tracked_domains_count')
                    ->label('Tracked domains')
                    ->counts([
                        'trackedDomains' => fn (Builder $query): Builder => $query->whereNull('user_id'),
                    ])
                    ->badge()
                    ->color(fn (mixed $state): string => (int) $state === 0 ? 'warning' : 'gray')
                    ->icon(fn (mixed $state): ?Heroicon => (int) $state === 0 ? Heroicon::ExclamationTriangle : null)
                    ->tooltip(fn (mixed $state): ?string => (int) $state === 0 ? 'No domains are tracked for this customer' : null)
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
