<?php

namespace App\Integrations\Exports;

use App\DataTransferObjects\ExportFormat;
use App\DataTransferObjects\ExportParameters;
use Illuminate\Support\Collection;

interface ExportProviderInterface
{
    /**
     * @return Collection<int, ExportFormat>
     */
    public function exportFormats(): Collection;

    public function createExport(string $key, ExportParameters $period): ExportInterface;

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): static;
}
