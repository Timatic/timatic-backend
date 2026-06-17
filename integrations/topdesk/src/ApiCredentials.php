<?php

namespace Timatic\Topdesk;

final readonly class ApiCredentials
{
    public function __construct(
        public string $baseUrl,
        public string $username,
        public string $apiToken,
    ) {}
}
