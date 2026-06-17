<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SocialiteRedirecting
{
    use Dispatchable;

    /** @var array<string> */
    private array $scopes = [];

    public function addScopes(string ...$scopes): void
    {
        $this->scopes = array_merge($this->scopes, $scopes);
    }

    /** @return array<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }
}
