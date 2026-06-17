<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('given_name')->label('First name'),
                TextColumn::make('family_name')->label('Last name'),
                TextColumn::make('roles.name')->badge()->label('Roles'),
                TextColumn::make('created_at')->toggleable(isToggledHiddenByDefault: true)->dateTime()->sortable(),
            ]);
    }
}
