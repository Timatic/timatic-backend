<?php

use App\DataTransferObjects\ExportPeriod;
use App\Exports\CoreExportProvider;
use App\Integrations\ExportProviderRegistry;
use App\Integrations\ExportService;
use App\Models\Integration;

it('throws when providers register duplicate format keys', function () {
    Integration::create(['name' => 'Duplicate core', 'type' => 'duplicate-core', 'config' => []]);

    $registry = new ExportProviderRegistry;
    $registry->register('duplicate-core', CoreExportProvider::class);

    (new ExportService($registry, new CoreExportProvider))->formats();
})->throws(RuntimeException::class, 'Duplicate export format keys');

it('throws when creating an export for an unknown format', function () {
    $registry = new ExportProviderRegistry;

    (new ExportService($registry, new CoreExportProvider))->createExport('unknown-format', new ExportPeriod(2026, 6));
})->throws(InvalidArgumentException::class, 'Unknown export format: unknown-format');
