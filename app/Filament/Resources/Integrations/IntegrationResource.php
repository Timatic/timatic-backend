<?php

namespace App\Filament\Resources\Integrations;

use App\Filament\Resources\Integrations\Schemas\IntegrationForm;
use App\Filament\Resources\Integrations\Tables\IntegrationsTable;
use App\Integrations\IntegrationTypeRegistry;
use App\Models\Integration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class IntegrationResource extends Resource
{
    protected static ?string $model = Integration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    public static function form(Schema $form): Schema
    {
        return IntegrationForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return IntegrationsTable::configure($table);
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => Pages\ListIntegrations::route('/'),
        ];

        foreach (app(IntegrationTypeRegistry::class)->allPageEntries() as $routeKey => $pageClass) {
            $suffix = '/'.Str::replace('.', '-', $routeKey);
            $pages[$routeKey] = $pageClass::route('/{record}'.$suffix);
        }

        return $pages;
    }
}
