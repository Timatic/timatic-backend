<?php

namespace App\Filament\Resources\OvertimeRules\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class OvertimeRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TimePicker::make('start_time')
                    ->required()
                    ->seconds(false),
                TimePicker::make('end_time')
                    ->required()
                    ->seconds(false),
                CheckboxList::make('days')
                    ->required()
                    ->options([
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                        7 => 'Sunday',
                        'holiday' => 'Holiday',
                    ])
                    ->columns(4),
                TextInput::make('percentage')
                    ->required()
                    ->numeric()
                    ->suffix('%'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
