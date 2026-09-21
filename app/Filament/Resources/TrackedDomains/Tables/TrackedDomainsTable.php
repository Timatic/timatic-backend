<?php

namespace App\Filament\Resources\TrackedDomains\Tables;

use App\Models\TrackedDomain;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TrackedDomainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->state(fn (TrackedDomain $record): string => $record->domain.$record->path),
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('budget')
                    ->label('Budget')
                    ->state(fn (TrackedDomain $record): ?string => $record->budget?->getTitle()),
                IconColumn::make('is_internal')->label('Internal')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('user.email')->label('Private to')->placeholder('Shared'),
                TextColumn::make('createdBy.email')->label('Added by'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
