<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

readonly class HoursExportRow
{
    /**
     * @param  array<int, float>  $hoursPerMonth
     */
    public function __construct(
        public string $group,
        public ?string $split,
        public array $hoursPerMonth,
    ) {}
}
