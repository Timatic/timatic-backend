<?php

namespace Timatic\Tempo\DataTransferObjects;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Timatic\Jira\DataTransferObjects\JiraWorklog;

readonly class TempoWorklogPage
{
    /** @param Collection<int, JiraWorklog> $worklogs */
    public function __construct(
        public Collection $worklogs,
        public ?CarbonImmutable $latestUpdatedAt,
        public bool $hasNext,
    ) {}
}
