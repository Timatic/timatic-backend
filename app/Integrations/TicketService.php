<?php

namespace App\Integrations;

use App\DataTransferObjects\Ticket;
use App\Integrations\Contracts\TicketProviderInterface;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Collection;

class TicketService
{
    public function __construct(private readonly TicketProviderRegistry $registry) {}

    /** @return Collection<int, Ticket> */
    public function searchTickets(?Customer $customer, ?string $search, ?User $user): Collection
    {
        return $this->resolveProviders()
            ->flatMap(fn (TicketProviderInterface $provider) => $provider->searchTickets($customer, $search, $user));
    }

    public function fetchTicketDetails(string $key): ?Ticket
    {
        foreach ($this->resolveProviders() as $provider) {
            $details = $provider->fetchTicketDetails($key);

            if ($details !== null) {
                return $details;
            }
        }

        return null;
    }

    public function fetchTicketByKey(string $key): ?Ticket
    {
        foreach ($this->resolveProviders() as $provider) {
            $ticket = $provider->fetchTicketByKey($key);

            if ($ticket !== null) {
                return $ticket;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public function ticketKeyPatterns(): array
    {
        return array_map(fn ($class) => $class::ticketKeyPattern(), $this->registry->all());
    }

    /** @return Collection<int, TicketProviderInterface> */
    private function resolveProviders(): Collection
    {
        return collect(Integration::whereIn('type', array_keys($this->registry->all()))->get())
            ->map(fn (Integration $integration) => $this->registry->makeProvider(
                $integration->type,
                [...($integration->config ?? []), 'integration_id' => $integration->id],
            ));
    }
}
