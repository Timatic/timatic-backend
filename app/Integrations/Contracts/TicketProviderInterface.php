<?php

namespace App\Integrations\Contracts;

use App\DataTransferObjects\Ticket;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Collection;

interface TicketProviderInterface
{
    /**
     * @return Collection<int, Ticket>
     */
    public function searchTickets(?Customer $customer, ?string $search = null, ?User $user = null): Collection;

    public static function ticketKeyPattern(): string;

    public function fetchTicketByKey(string $key): ?Ticket;

    public function fetchTicketDetails(string $key): ?Ticket;

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): static;
}
