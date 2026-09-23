<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class UnknownApiClientException extends Exception
{
    public static function forId(string $clientId): self
    {
        return new self("There is no api client registered under [{$clientId}].");
    }
}
