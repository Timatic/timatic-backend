<?php

declare(strict_types=1);

namespace Timatic\Jira\DataTransferObjects;

readonly class JiraCloudResource
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
