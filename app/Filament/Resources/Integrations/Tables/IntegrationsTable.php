<?php

namespace App\Filament\Resources\Integrations\Tables;

use App\Integrations\IntegrationTypeRegistry;
use App\Models\Integration;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IntegrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordUrl(function (Integration $record): ?string {
                $pageClass = app(IntegrationTypeRegistry::class)->resolveLandingPageClass($record->type, $record);

                return $pageClass
                    ? $pageClass::getUrl(['record' => $record->id])
                    : null;
            });
    }
}
