<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\ApiToken;

class IssuedApiToken
{
    public function __construct(
        public readonly ApiToken $apiToken,
        public readonly string $plainTextToken,
    ) {}
}
