<?php

namespace Timatic\Topdesk\DataTransferObjects;

final readonly class TopdeskBranch
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $clientReferenceNumber,
    ) {}
}
