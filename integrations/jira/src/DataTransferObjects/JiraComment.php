<?php

namespace Timatic\Jira\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class JiraComment
{
    public function __construct(
        public string $id,
        public string $authorDisplayName,
        public ?string $authorEmail,
        public string $body,
        public CarbonImmutable $createdAt,
    ) {}
}
