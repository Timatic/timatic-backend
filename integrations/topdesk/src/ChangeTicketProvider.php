<?php

namespace Timatic\Topdesk;

use App\DataTransferObjects\Ticket;
use App\DataTransferObjects\TicketAction;
use App\Integrations\Contracts\TicketProviderInterface;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Collection;
use Timatic\Topdesk\DataTransferObjects\TopdeskAction;
use Timatic\Topdesk\DataTransferObjects\TopdeskChange;
use Timatic\Topdesk\Requests\GetChangeByNumberRequest;
use Timatic\Topdesk\Requests\GetChangeProgressTrailRequest;
use Timatic\Topdesk\Requests\GetChangesRequest;
use Timatic\Topdesk\Services\FiqlBuilder;
use Timatic\Topdesk\Services\TopdeskBranchResolver;

final class ChangeTicketProvider implements TicketProviderInterface
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
        $integration = Integration::where('type', 'topdesk')->first();

        return $integration?->config['change_key_pattern'] ?? '[A-Z]+\s?\d+';
    }

    /** @return Collection<int, Ticket> */
    public function searchTickets(?Customer $customer, ?string $search = null, ?User $user = null): Collection
    {
        $branchId = null;

        if ($customer !== null) {
            $branchId = $this->branchResolver->resolveBranchId($customer);

            if (! $branchId) {
                return collect();
            }
        }

        $fiql = FiqlBuilder::build($branchId, $search, 'branch.id', self::ticketKeyPattern());

        if ($fiql === null) {
            return collect();
        }

        /** @var array<int, TopdeskChange> $changes */
        $changes = $this->connector->send(new GetChangesRequest($fiql))->dto() ?? [];

        return collect($changes)->map(fn (TopdeskChange $change) => $this->mapToTicket($change));
    }

    public function fetchTicketByKey(string $key): ?Ticket
    {
        $change = $this->connector->send(new GetChangeByNumberRequest($key))->dto();

        if ($change === null) {
            return null;
        }

        $customer = $this->branchResolver->customerForBranchId($change->branchId);

        return $this->mapToTicket($change)->withMapping($customer?->id, null);
    }

    public function fetchTicketDetails(string $key): ?Ticket
    {
        $change = $this->connector->send(new GetChangeByNumberRequest($key))->dto();

        if ($change === null) {
            return null;
        }

        /** @var array<int, TopdeskAction> $rawActions */
        $rawActions = $this->connector->send(new GetChangeProgressTrailRequest($key))->dto() ?? [];

        $customer = $this->branchResolver->customerForBranchId($change->branchId);

        return $this->mapToTicket($change, $this->mapActions($rawActions))->withMapping($customer?->id, null);
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
    private function mapToTicket(TopdeskChange $change, ?Collection $actions = null): Ticket
    {
        return new Ticket(
            type: 'change',
            id: $change->id,
            number: $change->number,
            title: $change->briefDescription,
            created_at: $change->creationDate,
            closed_at: $change->closedDate,
            url: rtrim($this->baseUrl, '/').'/tas/secure/change?action=show&unid='.$change->id,
            actions: $actions ?? collect(),
        );
    }
}
