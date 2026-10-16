<?php

namespace App\Integrations;

use App\DataTransferObjects\Ticket;
use App\DataTransferObjects\TicketMapping;
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
        // A key outside every mapped project can still exist at a provider, so then ask them all.
        $providers = $this->providersMatching($key)
            ->whenEmpty(fn () => $this->resolveProviders());

        foreach ($providers as $provider) {
            $details = $provider->fetchTicketDetails($key);

            if ($details !== null) {
                return $details;
            }
        }

        return null;
    }

    public function fetchTicketByKey(string $key): ?Ticket
    {
        // A key outside every mapped project can still exist at a provider, so then ask them all.
        $providers = $this->providersMatching($key)
            ->whenEmpty(fn () => $this->resolveProviders());

        foreach ($providers as $provider) {
            $ticket = $provider->fetchTicketByKey($key);

            if ($ticket !== null) {
                return $ticket;
            }
        }

        return null;
    }

    public function findMappingByTicketKey(string $key): ?TicketMapping
    {
        foreach ($this->providersMatching($key) as $provider) {
            $mapping = $provider->findMappingByTicketKey($key);

            if ($mapping !== null) {
                return $mapping;
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
    private function providersMatching(string $key): Collection
    {
        return $this->resolveProviders()
            ->filter(fn (TicketProviderInterface $provider) => preg_match('/^(?:'.$provider::ticketKeyPattern().')$/i', $key) === 1);
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
