<?php

namespace Timatic\GoogleCalendar\DataTransferObjects;

use Carbon\CarbonImmutable;

class GoogleTokens
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly int $expiresIn,
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
