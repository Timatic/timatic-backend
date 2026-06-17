<?php

namespace Timatic\Jira\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class JiraChangelogEntry
{
    public function __construct(
        public string $id,
        public string $authorDisplayName,
        public ?string $authorEmail,
        public string $fromStatus,
        public string $toStatus,
        public CarbonImmutable $createdAt,
    ) {}
}
