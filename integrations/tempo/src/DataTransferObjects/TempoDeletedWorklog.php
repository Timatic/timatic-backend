<?php

namespace Timatic\Tempo\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class TempoDeletedWorklog
{
    public function __construct(
        public string $id,
        public CarbonImmutable $deletedAt,
    ) {}
}
