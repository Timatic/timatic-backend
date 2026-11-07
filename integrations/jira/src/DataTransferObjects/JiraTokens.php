<?php

declare(strict_types=1);

namespace Timatic\Jira\DataTransferObjects;

readonly class JiraTokens
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresIn,
    ) {}
}
