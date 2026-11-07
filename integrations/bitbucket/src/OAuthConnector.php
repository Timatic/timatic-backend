<?php

declare(strict_types=1);

namespace Timatic\Bitbucket;

use Saloon\Http\Connector as SaloonConnector;

class OAuthConnector extends SaloonConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://bitbucket.org';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }
}
