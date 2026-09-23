<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InvalidAuthorizationCodeException extends Exception
{
    private function __construct(string $message, private readonly int $status)
    {
        parent::__construct($message);
    }

    public static function unknown(): self
    {
        return new self('The authorization code is unknown, expired or already used.', 410);
    }

    public static function verifierMismatch(): self
    {
        return new self('The code verifier does not match the code challenge.', 400);
    }

    public static function redirectUriMismatch(): self
    {
        return new self('The redirect uri does not match the one used to request the code.', 400);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'errors' => [
                ['status' => (string) $this->status, 'title' => $this->getMessage()],
            ],
        ], $this->status);
    }
}
