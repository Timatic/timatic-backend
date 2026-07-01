<?php

namespace Timatic\Topdesk;

use App\DataTransferObjects\Ticket;
use App\DataTransferObjects\TicketAction;
use App\Integrations\Contracts\TicketProviderInterface;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Collection;
use Timatic\Topdesk\DataTransferObjects\TopdeskAction;
use Timatic\Topdesk\DataTransferObjects\TopdeskIncident;
use Timatic\Topdesk\Requests\GetIncidentActionsRequest;
use Timatic\Topdesk\Requests\GetIncidentByNumberRequest;
use Timatic\Topdesk\Requests\GetIncidentsRequest;
use Timatic\Topdesk\Services\FiqlBuilder;
use Timatic\Topdesk\Services\TopdeskBranchResolver;

final class IncidentTicketProvider implements TicketProviderInterface
{
    public function __construct(
        private readonly Connector $connector,
        private readonly TopdeskBranchResolver $branchResolver,
        private readonly string $baseUrl,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): static
    {
        return new self(
            connector: app(Connector::class),
            branchResolver: app(TopdeskBranchResolver::class),
            baseUrl: $config['base_url'] ?? '',
        );
    }

    public static function ticketKeyPattern(): string
    {
        return FiqlBuilder::ticketKeyPattern();
    }

    /** @return Collection<int, Ticket> */
    public function searchTickets(?Customer $customer, ?string $search = null, ?User $user = null): Collection
    {
        $fiql = FiqlBuilder::build($this->branchResolver, $customer, $search, 'callerBranch.id');

        if ($fiql === null) {
            return collect();
        }

        /** @var array<int, TopdeskIncident> $incidents */
        $incidents = $this->connector->send(new GetIncidentsRequest($fiql))->dto() ?? [];

        return collect($incidents)->map(fn (TopdeskIncident $incident) => $this->mapToTicket($incident));
    }

    public function fetchTicketByKey(string $key): ?Ticket
    {
        $incident = $this->connector->send(new GetIncidentByNumberRequest($key))->dto();

        if ($incident === null) {
            return null;
        }

        $customer = Customer::where('external_id', $incident->callerBranchClientReferenceNumber)->first();

        return $this->mapToTicket($incident)->withMapping($customer?->id, null);
    }

    public function fetchTicketDetails(string $key): ?Ticket
    {
        $incident = $this->connector->send(new GetIncidentByNumberRequest($key))->dto();

        if ($incident === null) {
            return null;
        }

        /** @var array<int, TopdeskAction> $rawActions */
        $rawActions = $this->connector->send(new GetIncidentActionsRequest($key))->dto() ?? [];

        $customer = Customer::where('external_id', $incident->callerBranchClientReferenceNumber)->first();

        return $this->mapToTicket($incident, $this->mapActions($rawActions))->withMapping($customer?->id, null);
    }

    /**
     * @param  array<int, TopdeskAction>  $rawActions
     * @return Collection<int, TicketAction>
     */
    private function mapActions(array $rawActions): Collection
    {
        return collect($rawActions)
            ->map(fn (TopdeskAction $action) => new TicketAction(
                id: $action->id,
                entryDate: $action->entryDate,
                text: $action->memoText,
                personDisplayName: $action->operatorName ?? $action->personName,
                personEmail: null,
            ))
            ->filter(fn (TicketAction $action) => $action->text !== '')
            ->values();
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
