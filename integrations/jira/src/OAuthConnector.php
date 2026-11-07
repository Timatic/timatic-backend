<?php

declare(strict_types=1);

namespace Timatic\Jira;

use Saloon\Http\Connector as SaloonConnector;

class OAuthConnector extends SaloonConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://auth.atlassian.com';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }
}
