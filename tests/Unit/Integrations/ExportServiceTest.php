<?php

use App\DataTransferObjects\ExportPeriod;
use App\Exports\CoreExportProvider;
use App\Integrations\ExportProviderRegistry;
use App\Integrations\ExportService;

it('throws when providers register duplicate format keys', function () {
    $registry = new ExportProviderRegistry;
    $registry->registerGlobal(CoreExportProvider::class);
    $registry->registerGlobal(CoreExportProvider::class);

    (new ExportService($registry))->formats();
})->throws(RuntimeException::class, 'Duplicate export format keys');

it('throws when creating an export for an unknown format', function () {
    $registry = new ExportProviderRegistry;
    $registry->registerGlobal(CoreExportProvider::class);

    (new ExportService($registry))->createExport('unknown-format', new ExportPeriod(2026, 6));
})->throws(InvalidArgumentException::class, 'Unknown export format: unknown-format');
