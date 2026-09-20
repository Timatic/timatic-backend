<?php

namespace App\Filament\Resources\TrackedDomains\Pages;

use App\Filament\Resources\TrackedDomains\TrackedDomainResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrackedDomain extends EditRecord
{
    protected static string $resource = TrackedDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
