<?php

namespace Timatic\GoogleCalendar\DataTransferObjects;

class RefreshedTokens
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly int $expiresIn,
    ) {}
}
