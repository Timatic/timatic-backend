<?php

namespace Timatic\Tempo\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class TempoWorklog
{
    public function __construct(
        public string $id,
        public string $issueId,
        public string $authorAccountId,
        public CarbonImmutable $startedAt,
        public int $timeSpentSeconds,
        public string $description,
    ) {}
}
