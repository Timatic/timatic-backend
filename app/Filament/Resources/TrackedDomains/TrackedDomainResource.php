<?php

namespace App\Filament\Resources\TrackedDomains;

use App\Filament\Resources\TrackedDomains\Schemas\TrackedDomainForm;
use App\Filament\Resources\TrackedDomains\Tables\TrackedDomainsTable;
use App\Models\TrackedDomain;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class TrackedDomainResource extends Resource
{
    protected static ?string $model = TrackedDomain::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    public static function form(Schema $form): Schema
    {
        return TrackedDomainForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return TrackedDomainsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrackedDomains::route('/'),
            'create' => Pages\CreateTrackedDomain::route('/create'),
            'edit' => Pages\EditTrackedDomain::route('/{record}/edit'),
        ];
    }
}
