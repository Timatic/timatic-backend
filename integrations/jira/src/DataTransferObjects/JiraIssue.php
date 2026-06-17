<?php

namespace Timatic\Jira\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class JiraIssue
{
    public function __construct(
        public string $key,
        public string $issueType,
        public string $summary,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $resolvedAt,
    ) {}
}
