<?php

namespace App\DataTransferObjects;

use App\Enums\ExportDateRequirement;

readonly class ExportFormat
{
    public function __construct(
        public string $key,
        public string $label,
        public ExportDateRequirement $dateRequirement,
        public string $extension = 'xlsx',
    ) {}
}
