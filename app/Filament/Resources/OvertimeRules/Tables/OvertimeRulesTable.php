<?php

namespace App\Filament\Resources\OvertimeRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OvertimeRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('key')
                    ->searchable(),
                TextColumn::make('start_time'),
                TextColumn::make('end_time'),
                TextColumn::make('days')
                    ->state(fn ($record) => collect((array) $record->days)
                        ->map(fn ($day) => match ($day) {
                            1 => 'Mon', 2 => 'Tue', 3 => 'Wed',
                            4 => 'Thu', 5 => 'Fri', 6 => 'Sat',
                            7 => 'Sun', 'holiday' => 'Holiday',
                            default => $day,
                        })
                        ->join(', ')
                    ),
                TextColumn::make('percentage')
                    ->suffix('%'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
