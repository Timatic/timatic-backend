<?php

use App\Integrations\TicketService;
use App\Models\Customer;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Topdesk\ChangeTicketProvider;
use Timatic\Topdesk\Requests\GetBranchesRequest;
use Timatic\Topdesk\Requests\GetChangeByNumberRequest;
use Timatic\Topdesk\Requests\GetChangeProgressTrailRequest;
use Timatic\Topdesk\Requests\GetChangesRequest;
use Timatic\Topdesk\Requests\GetIncidentsRequest;

uses(RefreshDatabase::class);

afterEach(fn () => MockClient::destroyGlobal());

it('calls both the incident and change providers for a topdesk integration', function () {
    Integration::create([
        'name' => 'TOPdesk',
        'type' => 'topdesk',
        'config' => ['base_url' => 'https://acme.topdesk.net', 'username' => 'u', 'api_token' => 't'],
    ]);
    $customer = Customer::factory()->create(['external_id' => 'CUST-1']);

    MockClient::global([
        GetBranchesRequest::class => MockResponse::make([
            ['id' => 'branch-1', 'name' => 'Acme', 'clientReferenceNumber' => 'CUST-1'],
        ]),
        GetIncidentsRequest::class => MockResponse::make([
            [
                'id' => 'inc-1',
                'number' => 'I 2601 001',
                'briefDescription' => 'Broken laptop',
                'creationDate' => '2026-06-01T10:00:00+0000',
                'callerBranch' => ['id' => 'branch-1', 'clientReferenceNumber' => 'CUST-1'],
            ],
        ]),
        GetChangesRequest::class => MockResponse::make([
            'results' => [
                [
                    'id' => 'chg-1',
                    'number' => 'C 2601 001',
                    'briefDescription' => 'Migrate server',
                    'creationDate' => '2026-06-02T10:00:00+0000',
                    'branch' => ['id' => 'branch-1', 'name' => 'Acme'],
                ],
            ],
        ]),
    ]);

    $tickets = app(TicketService::class)->searchTickets($customer, null, null);

    expect($tickets->pluck('type')->sort()->values()->all())->toBe(['change', 'incident'])
        ->and($tickets->pluck('number')->sort()->values()->all())->toBe(['C 2601 001', 'I 2601 001']);
});

it('fetches change details with progress trail actions and resolves the customer', function () {
    Integration::create([
        'name' => 'TOPdesk',
        'type' => 'topdesk',
        'config' => ['base_url' => 'https://acme.topdesk.net', 'username' => 'u', 'api_token' => 't'],
    ]);
    $customer = Customer::factory()->create(['external_id' => 'CUST-1']);

    MockClient::global([
        GetChangeByNumberRequest::class => MockResponse::make([
            'id' => 'chg-1',
            'number' => 'C 2601 001',
            'briefDescription' => 'Migrate server',
            'creationDate' => '2026-06-02T10:00:00+0000',
            'simple' => ['closedDate' => '2026-06-05T12:00:00+0000'],
            'branch' => ['id' => 'branch-1'],
        ]),
        GetChangeProgressTrailRequest::class => MockResponse::make([
            'results' => [
                [
                    'id' => 'pt-1',
                    'plainText' => 'Started work',
                    'entryDate' => '2026-06-03T09:00:00+0000',
                    'operator' => ['name' => 'Jane'],
                    'type' => 'memo',
                ],
            ],
        ]),
        GetBranchesRequest::class => MockResponse::make([
            ['id' => 'branch-1', 'name' => 'Acme', 'clientReferenceNumber' => 'CUST-1'],
        ]),
    ]);

    $ticket = ChangeTicketProvider::fromConfig(['base_url' => 'https://acme.topdesk.net'])
        ->fetchTicketDetails('C 2601 001');

    expect($ticket->type)->toBe('change')
        ->and($ticket->closed_at?->toDateString())->toBe('2026-06-05')
        ->and($ticket->customer_id)->toBe($customer->id)
        ->and($ticket->actions)->toHaveCount(1)
        ->and($ticket->actions->first()->text)->toBe('Started work');
});
