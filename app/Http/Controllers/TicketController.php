<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketCollectionRequest;
use App\Http\Resources\Ticket as TicketResource;
use App\Integrations\TicketService;
use App\Models\Customer;
use App\Models\User;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class TicketController extends Controller
{
    public function index(TicketCollectionRequest $request, TicketService $ticketService): JsonApiResourceCollection
    {
        $customerId = $request->input('filter.customerId');
        /** @var Customer|null $customer */
        $customer = $customerId ? Customer::findOrFail($customerId) : null;

        $search = $request->input('filter.search');
        $user = $request->user() instanceof User ? $request->user() : null;

        return TicketResource::collection($ticketService->searchTickets($customer, $search, $user));
    }

    public function show(string $key, TicketService $ticketService): TicketResource
    {
        $ticket = $ticketService->fetchTicketDetails($key);

        abort_if($ticket === null, 404);

        return TicketResource::make($ticket);
    }
}
