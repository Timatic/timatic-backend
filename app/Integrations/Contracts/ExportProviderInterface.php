<?php

namespace App\Integrations\Contracts;

use App\DataTransferObjects\ExportFormat;
use App\DataTransferObjects\ExportPeriod;
use Illuminate\Support\Collection;

interface ExportProviderInterface
{
    /**
     * @return Collection<int, ExportFormat>
     */
    public function exportFormats(): Collection;

    public function createExport(string $key, ExportPeriod $period): ExportInterface;

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): static;
}
