<?php

namespace Timatic\Topdesk\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class TopdeskChange
{
    public function __construct(
        public string $id,
        public string $number,
        public string $briefDescription,
        public CarbonImmutable $creationDate,
        public ?CarbonImmutable $closedDate,
        public ?string $branchId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            number: $data['number'],
            briefDescription: $data['briefDescription'] ?? '',
            creationDate: CarbonImmutable::parse($data['creationDate']),
            closedDate: isset($data['simple']['closedDate'])
                ? CarbonImmutable::parse($data['simple']['closedDate'])
                : null,
            branchId: $data['branch']['id'] ?? null,
        );
    }
}
