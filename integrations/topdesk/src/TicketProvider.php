<?php

namespace Timatic\Topdesk;

use App\DataTransferObjects\Ticket;
use App\DataTransferObjects\TicketAction;
use App\Integrations\Contracts\TicketProviderInterface;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Timatic\Topdesk\DataTransferObjects\TopdeskAction;
use Timatic\Topdesk\DataTransferObjects\TopdeskIncident;
use Timatic\Topdesk\Requests\GetBranchesRequest;
use Timatic\Topdesk\Requests\GetIncidentActionsRequest;
use Timatic\Topdesk\Requests\GetIncidentByNumberRequest;
use Timatic\Topdesk\Requests\GetIncidentsRequest;

final class TicketProvider implements TicketProviderInterface
{
    public function __construct(
        private readonly Connector $connector,
        private readonly string $baseUrl,
        private readonly string $branchMatchField,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): static
    {
        return new self(
            connector: app(Connector::class),
            baseUrl: $config['base_url'] ?? '',
            branchMatchField: $config['branch_match_field'] ?? 'clientReferenceNumber',
        );
    }

    private const RECENT_WEEKS = 3;

    /** @return Collection<int, Ticket> */
    public function searchTickets(?Customer $customer, ?string $search = null, ?User $user = null): Collection
    {
        $parts = [];

        if ($customer !== null) {
            $branchId = $this->resolveBranchId($customer);

            if ($branchId === null) {
                return collect();
            }

            $parts[] = 'callerBranch.id=='.$branchId;
        } elseif ($search === null || $search === '') {
            return collect();
        }

        if ($search !== null && $search !== '') {
            $parts[] = preg_match('/^'.self::ticketKeyPattern().'$/i', $search)
                ? 'number=='.$search
                : 'briefDescription=contains='.$search;
        } else {
            $since = now()->subWeeks(self::RECENT_WEEKS)->toIso8601String();
            $parts[] = "modificationDate=gt='{$since}'";
        }

        return $this->fetchIncidentsByFiql(implode(';', $parts));
    }

    public static function ticketKeyPattern(): string
    {
        $pattern = Integration::where('type', 'topdesk')->value('config->ticket_key_pattern');

        return $pattern ?? '[A-Z]+\s?\d+';
    }

    public function fetchTicketByKey(string $key): ?Ticket
    {
        $response = $this->connector->send(new GetIncidentByNumberRequest($key));

        if (! $response->successful()) {
            return null;
        }

        $incident = $response->dto();

        if ($incident === null) {
            return null;
        }

        $customer = $this->resolveCustomerFromBranch($incident);

        return $this->mapToTicket($incident)->withMapping($customer?->id, null);
    }

    public function fetchTicketDetails(string $key): ?Ticket
    {
        $response = $this->connector->send(new GetIncidentByNumberRequest($key));

        if (! $response->successful()) {
            return null;
        }

        $incident = $response->dto();

        if ($incident === null) {
            return null;
        }

        /** @var array<int, TopdeskAction> $rawActions */
        $rawActions = $this->connector->send(new GetIncidentActionsRequest($key))->dto() ?? [];

        $actions = collect($rawActions)
            ->map(fn (TopdeskAction $action) => new TicketAction(
                id: $action->id,
                entryDate: $action->entryDate,
                text: $action->memoText,
                personDisplayName: $action->operatorName ?? $action->personName,
                personEmail: null,
            ))
            ->filter(fn (TicketAction $action) => $action->text !== '')
            ->values();

        $customer = $this->resolveCustomerFromBranch($incident);

        return $this->mapToTicket($incident, $actions)->withMapping($customer?->id, null);
    }

    private function resolveBranchId(Customer $customer): ?string
    {
        if ($customer->external_id === null) {
            return null;
        }

        $cacheKey = 'topdesk.branch_id.'.md5($this->baseUrl.$customer->external_id.$this->branchMatchField);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($customer): ?string {
            $branch = $this->connector->send(
                new GetBranchesRequest("archived==false;{$this->branchMatchField}=={$customer->external_id}")
            )->dto();

            return $branch?->id;
        });
    }

    private function resolveCustomerFromBranch(TopdeskIncident $incident): ?Customer
    {
        if ($incident->callerBranchClientReferenceNumber === null) {
            return null;
        }

        return Customer::where('external_id', $incident->callerBranchClientReferenceNumber)->first();
    }

    /** @return Collection<int, Ticket> */
    private function fetchIncidentsByFiql(string $fiql): Collection
    {
        /** @var array<int, TopdeskIncident> $incidents */
        $incidents = $this->connector->send(new GetIncidentsRequest($fiql))->dto() ?? [];

        return collect($incidents)->map(fn (TopdeskIncident $incident) => $this->mapToTicket($incident));
    }

    /**
     * @param  Collection<int, TicketAction>|null  $actions
     */
    private function mapToTicket(TopdeskIncident $incident, ?Collection $actions = null): Ticket
    {
        return new Ticket(
            type: 'incident',
            id: $incident->id,
            number: $incident->number,
            title: $incident->briefDescription,
            created_at: $incident->creationDate,
            closed_at: $incident->closedDate,
            url: rtrim($this->baseUrl, '/').'/tas/secure/incident?action=show&unid='.$incident->id,
            actions: $actions ?? collect(),
        );
    }
}
