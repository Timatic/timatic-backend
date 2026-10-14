<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

readonly class SuggestionIntegrationStatus
{
    public function __construct(
        public string $key,
        public string $label,
        public string $instructions,
        public bool $isCompanyManaged,
        public bool $isActive,
        public ?string $connectUrl,
    ) {}
}
