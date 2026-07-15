<?php

namespace App\DataTransferObjects;

use App\Enums\ExportPeriodOptions;

readonly class ExportFormat
{
    public function __construct(
        public string $key,
        public string $label,
        public ExportPeriodOptions $periodOptions,
        public string $extension = 'xlsx',
    ) {}
}
