<?php

declare(strict_types=1);

namespace Timatic\GoogleCalendar\Exceptions;

use RuntimeException;

class CalendarConnectionFailedException extends RuntimeException
{
    public static function unknownState(): self
    {
        return new self('The OAuth state is unknown, expired or already used.');
    }

    public static function codeRefused(int $status): self
    {
        return new self("Google refused the authorization code with status {$status}.");
    }

    public static function missingRefreshToken(): self
    {
        return new self('Google granted calendar access without a refresh token.');
    }
}
