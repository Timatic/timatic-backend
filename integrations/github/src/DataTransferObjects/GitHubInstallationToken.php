<?php

namespace Timatic\GitHub\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class GitHubInstallationToken
{
    public function __construct(
        public string $token,
        public CarbonImmutable $expiresAt,
    ) {}
}
