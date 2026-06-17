<?php

namespace App\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class TicketAction
{
    public function __construct(
        public string $id,
        public CarbonImmutable $entryDate,
        public string $text,
        public ?string $personDisplayName,
        public ?string $personEmail,
    ) {}
}
