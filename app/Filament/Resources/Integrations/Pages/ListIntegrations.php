<?php

namespace App\Filament\Resources\Integrations\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Integrations\IntegrationTypeRegistry;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListIntegrations extends ListRecords
{
    protected static string $resource = IntegrationResource::class;

    protected function getHeaderActions(): array
    {
        $types = collect(app(IntegrationTypeRegistry::class)->allPageEntries())
            ->keys()
            ->mapWithKeys(fn (string $routeKey) => [
                str($routeKey)->before('.')->toString() => str($routeKey)->before('.')->title()->toString(),
            ])
            ->unique()
            ->all();

        return [
            CreateAction::make()->form([
                TextInput::make('name')->required(),
                Select::make('type')->options($types)->required(),
            ])->createAnother(false),
        ];
    }
}
