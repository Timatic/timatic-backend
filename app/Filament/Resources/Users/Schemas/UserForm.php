<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $form): Schema
    {
        return $form->schema([
            Select::make('roles')
                ->multiple()
                ->relationship('roles', 'name')
                ->options(Role::pluck('name', 'id'))
                ->preload(),
        ]);
    }
}
