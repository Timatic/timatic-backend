<?php

namespace App\Filament\Resources\TrackedDomains\Pages;

use App\Filament\Resources\TrackedDomains\TrackedDomainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrackedDomains extends ListRecords
{
    protected static string $resource = TrackedDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
