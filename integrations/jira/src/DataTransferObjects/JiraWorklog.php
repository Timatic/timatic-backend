<?php

namespace Timatic\Jira\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class JiraWorklog
{
    public function __construct(
        public string $id,
        public string $issueId,
        public string $authorAccountId,
        public CarbonImmutable $startedAt,
        public int $timeSpentSeconds,
        public string $comment,
    ) {}
}
