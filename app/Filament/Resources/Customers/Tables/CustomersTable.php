<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
