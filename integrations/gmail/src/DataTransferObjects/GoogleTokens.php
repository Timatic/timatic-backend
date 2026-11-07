<?php

declare(strict_types=1);

namespace Timatic\Gmail\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class GoogleTokens
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresIn,
    ) {}

    /**
     * A minute is taken off so a token that is about to lapse is refreshed rather than used for a
     * request that would be refused halfway through a sync.
     */
    public function expiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addSeconds($this->expiresIn - 60);
    }
}
