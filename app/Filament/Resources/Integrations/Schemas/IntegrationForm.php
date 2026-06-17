<?php

namespace App\Filament\Resources\Integrations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IntegrationForm
{
    public static function configure(Schema $form): Schema
    {
        return $form->components([
            TextInput::make('name')->required(),
            TextInput::make('type')->disabled(),
        ]);
    }
}
