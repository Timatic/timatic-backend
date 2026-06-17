<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('external_id')->label('External ID')->disabled(),
            TextInput::make('name')->required(),
            TextInput::make('hourly_rate')->numeric()->prefix('€'),
            Select::make('account_manager_user_id')
                ->label('Account manager')
                ->options(User::pluck('email', 'id'))
                ->searchable(),
        ]);
    }
}
