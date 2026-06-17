<?php

namespace Timatic\Topdesk\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class TopdeskAction
{
    public function __construct(
        public string $id,
        public string $memoText,
        public CarbonImmutable $entryDate,
        public ?string $operatorName,
        public ?string $personName,
    ) {}
}
