<?php

namespace App\DataTransferObjects;

/**
 * Where an integration says work on a ticket belongs, as configured in Timatic.
 */
readonly class TicketMapping
{
    public function __construct(
        public int $customer_id,
        public ?int $budget_id,
    ) {}
}
