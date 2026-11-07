<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\ApiToken;

readonly class IssuedApiToken
{
    public function __construct(
        public ApiToken $apiToken,
        public string $plainTextToken,
    ) {}
}
