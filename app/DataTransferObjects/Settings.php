<?php

namespace App\DataTransferObjects;

readonly class Settings
{
    public function __construct(
        public ?float $defaultHourlyRate,
    ) {}
}
