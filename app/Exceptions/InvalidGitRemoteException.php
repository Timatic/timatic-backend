<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InvalidGitRemoteException extends Exception
{
    public static function forUrl(string $url): self
    {
        return new self("[{$url}] is not a git remote url.");
    }
}
