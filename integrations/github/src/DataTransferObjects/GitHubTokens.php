<?php

declare(strict_types=1);

namespace Timatic\GitHub\DataTransferObjects;

readonly class GitHubTokens
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public ?int $expiresIn,
    ) {}
}
