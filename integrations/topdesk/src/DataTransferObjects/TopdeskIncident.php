<?php

namespace Timatic\Topdesk\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class TopdeskIncident
{
    public function __construct(
        public string $id,
        public string $number,
        public string $briefDescription,
        public CarbonImmutable $creationDate,
        public ?CarbonImmutable $closedDate,
        public ?string $callerBranchId,
        public ?string $callerBranchClientReferenceNumber,
    ) {}
}
