<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\User;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GitHub\Models\RepositoryMapping;
use Timatic\GitHub\Requests\GetIssueCommentsRequest;
use Timatic\GitHub\Requests\GetIssueRequest;
use Timatic\GitHub\Requests\GetIssuesRequest;
use Timatic\GitHub\Requests\SearchIssuesRequest;
use Timatic\GitHub\TicketProvider;

afterEach(function () {
    MockClient::destroyGlobal();
});

it('lists open issues of the repositories mapped to the customer', function () {
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    $customer = Customer::factory()->create();
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
        'customer_id' => $customer->id,
    ]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'other',
        'repository_full_name' => 'acme/other',
    ]);
    MockClient::global([
        GetIssuesRequest::class => MockResponse::make([
            [
                'number' => 42,
                'title' => 'Login fails on Safari',
                'html_url' => 'https://github.com/acme/api/issues/42',
                'state' => 'open',
                'repository_url' => 'https://api.github.com/repos/acme/api',
                'created_at' => '2026-09-01T10:00:00Z',
            ],
            [
                'number' => 43,
                'title' => 'Fix login',
                'html_url' => 'https://github.com/acme/api/pull/43',
                'state' => 'open',
                'repository_url' => 'https://api.github.com/repos/acme/api',
                'created_at' => '2026-09-02T10:00:00Z',
                'pull_request' => ['url' => 'https://api.github.com/repos/acme/api/pulls/43'],
            ],
        ]),
    ]);

    $tickets = TicketProvider::fromConfig(['integration_id' => $integration->id, 'installation_id' => 4242])
        ->searchTickets($customer);

    expect($tickets)->toHaveCount(1)
        ->and($tickets->first()->number)->toBe('acme/api#42')
        ->and($tickets->first()->type)->toBe('issue')
        ->and($tickets->first()->customer_id)->toEqual($customer->id);
});

it('searches issues within the mapped repositories', function () {
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
    ]);
    $mockClient = MockClient::global([
        SearchIssuesRequest::class => MockResponse::make(['total_count' => 0, 'items' => []]),
    ]);

    TicketProvider::fromConfig(['integration_id' => $integration->id, 'installation_id' => 4242])
        ->searchTickets(null, 'safari');

    $mockClient->assertSent(function ($request) {
        return $request->query()->get('q') === 'is:issue safari repo:acme/api';
    });
});

it('searches issues the user is involved in when no customer is given', function () {
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    $user = User::factory()->create(['github_login' => 'octocat']);
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
    ]);
    $mockClient = MockClient::global([
        SearchIssuesRequest::class => MockResponse::make(['total_count' => 0, 'items' => []]),
    ]);

    TicketProvider::fromConfig(['integration_id' => $integration->id, 'installation_id' => 4242])
        ->searchTickets(null, null, $user);

    $mockClient->assertSent(function ($request) {
        return $request->query()->get('q') === 'is:issue is:open involves:octocat repo:acme/api';
    });
});

it('returns the ticket for a repository issue key with its mapping', function () {
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    $customer = Customer::factory()->create();
    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state(['effective_to' => null]))
        ->create(['customer_id' => $customer->id]);
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
        'customer_id' => $customer->id,
        'budget_id' => $budget->id,
    ]);
    MockClient::global([
        GetIssueRequest::class => MockResponse::make([
            'number' => 42,
            'title' => 'Login fails on Safari',
            'html_url' => 'https://github.com/acme/api/issues/42',
            'state' => 'closed',
            'repository_url' => 'https://api.github.com/repos/acme/api',
            'created_at' => '2026-09-01T10:00:00Z',
            'closed_at' => '2026-09-03T10:00:00Z',
        ]),
    ]);

    $ticket = TicketProvider::fromConfig(['integration_id' => $integration->id, 'installation_id' => 4242])
        ->fetchTicketByKey('acme/api#42');

    expect($ticket->number)->toBe('acme/api#42')
        ->and($ticket->title)->toBe('Login fails on Safari')
        ->and($ticket->url)->toBe('https://github.com/acme/api/issues/42')
        ->and($ticket->closed_at->toDateString())->toBe('2026-09-03')
        ->and($ticket->customer_id)->toEqual($customer->id)
        ->and($ticket->budget_id)->toEqual($budget->id);
});

it('returns nothing for an issue key GitHub does not know', function () {
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    MockClient::global([
        GetIssueRequest::class => MockResponse::make(['message' => 'Not Found'], 404),
    ]);

    $ticket = TicketProvider::fromConfig(['integration_id' => $integration->id, 'installation_id' => 4242])
        ->fetchTicketByKey('acme/api#404');

    expect($ticket)->toBeNull();
});

it('returns nothing for a key that points at a pull request', function () {
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    MockClient::global([
        GetIssueRequest::class => MockResponse::make([
            'number' => 43,
            'title' => 'Fix login',
            'html_url' => 'https://github.com/acme/api/pull/43',
            'state' => 'open',
            'repository_url' => 'https://api.github.com/repos/acme/api',
            'created_at' => '2026-09-02T10:00:00Z',
            'pull_request' => ['url' => 'https://api.github.com/repos/acme/api/pulls/43'],
        ]),
    ]);

    $ticket = TicketProvider::fromConfig(['integration_id' => $integration->id, 'installation_id' => 4242])
        ->fetchTicketByKey('acme/api#43');

    expect($ticket)->toBeNull();
});

it('returns the comments of an issue as chronological actions', function () {
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    MockClient::global([
        GetIssueRequest::class => MockResponse::make([
            'number' => 42,
            'title' => 'Login fails on Safari',
            'html_url' => 'https://github.com/acme/api/issues/42',
            'state' => 'open',
            'repository_url' => 'https://api.github.com/repos/acme/api',
            'created_at' => '2026-09-01T10:00:00Z',
        ]),
        GetIssueCommentsRequest::class => MockResponse::make([
            ['id' => 2, 'user' => ['login' => 'octocat'], 'body' => 'Fixed in 1.2', 'created_at' => '2026-09-04T10:00:00Z'],
            ['id' => 1, 'user' => ['login' => 'hubber'], 'body' => 'Reproduced', 'created_at' => '2026-09-02T10:00:00Z'],
            ['id' => 3, 'user' => ['login' => 'octocat'], 'body' => '', 'created_at' => '2026-09-05T10:00:00Z'],
        ]),
    ]);

    $ticket = TicketProvider::fromConfig(['integration_id' => $integration->id, 'installation_id' => 4242])
        ->fetchTicketDetails('acme/api#42');

    expect($ticket->actions->pluck('text')->all())->toBe(['Reproduced', 'Fixed in 1.2'])
        ->and($ticket->actions->first()->personDisplayName)->toBe('hubber')
        ->and($ticket->actions->first()->personEmail)->toBeNull();
});

it('returns nothing while no installation is linked', function () {
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);

    $provider = TicketProvider::fromConfig(['integration_id' => $integration->id]);

    expect($provider->searchTickets(null, 'safari'))->toBeEmpty()
        ->and($provider->fetchTicketByKey('acme/api#42'))->toBeNull();
});

it('matches a repository issue key in text but not a bare issue number', function () {
    expect(preg_match('/\b('.TicketProvider::ticketKeyPattern().')\b/i', 'fix login acme/api#42', $matches))->toBe(1)
        ->and($matches[1])->toBe('acme/api#42')
        ->and(preg_match('/\b('.TicketProvider::ticketKeyPattern().')\b/i', 'fix login #42'))->toBe(0);
});
