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
        public ?string $id = null,
        public ?string $parentKey = null,
        // Jira's hierarchy: -1 for a sub-task, 0 for a story or task, 1 for an epic
        public int $hierarchyLevel = 0,
    ) {}
}
