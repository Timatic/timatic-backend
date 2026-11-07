<?php

declare(strict_types=1);

namespace Timatic\Bitbucket\DataTransferObjects;

readonly class BitbucketTokens
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresIn,
    ) {}
}
