<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

class ApiClient
{
    /**
     * @param  list<string>  $redirectUris
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly array $redirectUris,
        public readonly int $tokenLifetimeDays,
        public readonly bool $autoApprove,
    ) {}

    public function allowsRedirectUri(string $redirectUri): bool
    {
        return in_array($redirectUri, $this->redirectUris, true);
    }
}
