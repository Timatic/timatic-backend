<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

readonly class SuggestionIntegration
{
    public function __construct(
        public string $key,
        public string $label,
        public string $iconUrl,
        public string $instructions,
        public bool $isCompanyManaged,
        public bool $isActive,
        public ?string $connectUrl,
        public ?string $connectLabel,
    ) {}
}
