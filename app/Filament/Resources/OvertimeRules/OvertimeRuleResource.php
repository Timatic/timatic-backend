<?php

namespace App\Filament\Resources\OvertimeRules;

use App\Filament\Resources\OvertimeRules\Pages\CreateOvertimeRule;
use App\Filament\Resources\OvertimeRules\Pages\EditOvertimeRule;
use App\Filament\Resources\OvertimeRules\Pages\ListOvertimeRules;
use App\Filament\Resources\OvertimeRules\Schemas\OvertimeRuleForm;
use App\Filament\Resources\OvertimeRules\Tables\OvertimeRulesTable;
use App\Models\OvertimeRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OvertimeRuleResource extends Resource
{
    protected static ?string $model = OvertimeRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function form(Schema $schema): Schema
    {
        return OvertimeRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OvertimeRulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOvertimeRules::route('/'),
            'create' => CreateOvertimeRule::route('/create'),
            'edit' => EditOvertimeRule::route('/{record}/edit'),
        ];
    }
}
