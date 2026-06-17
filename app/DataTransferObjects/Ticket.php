<?php

namespace App\DataTransferObjects;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

readonly class Ticket
{
    /**
     * @param  Collection<int, TicketAction>  $actions
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $number,
        public string $title,
        public CarbonImmutable $created_at,
        public ?CarbonImmutable $closed_at,
        public string $url,
        public ?int $customer_id = null,
        public ?int $budget_id = null,
        public Collection $actions = new Collection,
    ) {}

    public function withMapping(?int $customerId, ?int $budgetId): self
    {
        return new self(
            type: $this->type,
            id: $this->id,
            number: $this->number,
            title: $this->title,
            created_at: $this->created_at,
            closed_at: $this->closed_at,
            url: $this->url,
            customer_id: $customerId,
            budget_id: $budgetId,
            actions: $this->actions,
        );
    }
}
