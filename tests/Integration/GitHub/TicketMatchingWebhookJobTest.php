<?php

use App\Integrations\TicketService;
use App\Models\Event;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GitHub\Jobs\ProcessWebhookJob;
use Timatic\GitHub\Models\RepositoryMapping;
use Timatic\GitHub\Requests\GetIssueRequest;

afterEach(function () {
    MockClient::destroyGlobal();
});

it('resolves a bare issue reference in a commit message against the delivering repository', function () {
    EventFacade::fake();
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    User::factory()->create(['email' => 'dev@example.com']);
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    $mapping = RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
    ]);
    MockClient::global([
        GetIssueRequest::class => MockResponse::make([
            'number' => 42,
            'title' => 'Login fails on Safari',
            'html_url' => 'https://github.com/acme/api/issues/42',
            'state' => 'open',
            'repository_url' => 'https://api.github.com/repos/acme/api',
            'created_at' => '2026-09-01T10:00:00Z',
        ]),
    ]);
    $payload = [
        'ref' => 'refs/heads/main',
        'repository' => ['full_name' => 'acme/api'],
        'commits' => [[
            'id' => 'abc123',
            'message' => 'fix login (#42)',
            'timestamp' => '2026-09-05T09:38:30+00:00',
            'author' => ['email' => 'dev@example.com', 'username' => 'octocat'],
        ]],
    ];

    new ProcessWebhookJob($payload, $mapping, 'push')->handle(app(TicketService::class));

    expect(Event::sole())
        ->ticket_number->toBe('acme/api#42')
        ->ticket_type->toBe('issue');
});

it('resolves a fully qualified issue key in a branch name', function () {
    EventFacade::fake();
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    User::factory()->create(['github_login' => 'octocat']);
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
    ]);
    MockClient::global([
        GetIssueRequest::class => MockResponse::make([
            'number' => 42,
            'title' => 'Login fails on Safari',
            'html_url' => 'https://github.com/acme/api/issues/42',
            'state' => 'open',
            'repository_url' => 'https://api.github.com/repos/acme/api',
            'created_at' => '2026-09-01T10:00:00Z',
        ]),
    ]);
    $payload = [
        'ref' => 'acme/api#42-login',
        'ref_type' => 'branch',
        'sender' => ['login' => 'octocat'],
        'repository' => ['full_name' => 'acme/api'],
    ];

    new ProcessWebhookJob($payload, null, 'create')->handle(app(TicketService::class));

    expect(Event::sole())
        ->event_type_id->toBe('branch_created')
        ->ticket_number->toBe('acme/api#42');
});

it('leaves an event without a ticket when nothing references one', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $payload = [
        'ref' => 'refs/heads/main',
        'repository' => ['full_name' => 'acme/api'],
        'commits' => [[
            'id' => 'abc123',
            'message' => 'composer update',
            'timestamp' => '2026-09-05T09:38:30+00:00',
            'author' => ['email' => 'dev@example.com', 'username' => 'octocat'],
        ]],
    ];

    new ProcessWebhookJob($payload, null, 'push')->handle(app(TicketService::class));

    expect(Event::sole())
        ->ticket_number->toBeNull()
        ->ticket_id->toBeNull();
});
