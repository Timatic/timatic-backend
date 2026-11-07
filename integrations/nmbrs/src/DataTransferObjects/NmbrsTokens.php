<?php

declare(strict_types=1);

namespace Timatic\Nmbrs\DataTransferObjects;

readonly class NmbrsTokens
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresIn,
    ) {}
}
