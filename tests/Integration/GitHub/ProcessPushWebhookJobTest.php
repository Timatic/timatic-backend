<?php

use App\Integrations\TicketService;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;
use Timatic\GitHub\Jobs\ProcessWebhookJob;
use Timatic\GitHub\Models\RepositoryMapping;

it('creates a commit_pushed event carrying the mapped customer', function () {
    EventFacade::fake();
    $user = User::factory()->create(['email' => 'dev@example.com']);
    $customer = Customer::factory()->create();
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    $mapping = RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
        'customer_id' => $customer->id,
    ]);
    $payload = [
        'ref' => 'refs/heads/feature/login',
        'forced' => false,
        'repository' => ['full_name' => 'acme/api'],
        'commits' => [[
            'id' => 'abc123',
            'message' => "fix login\n\nreset the session",
            'timestamp' => '2026-09-05T09:38:30+00:00',
            'author' => ['email' => 'dev@example.com', 'username' => 'octocat'],
        ]],
    ];

    new ProcessWebhookJob($payload, $mapping, 'push')->handle(app(TicketService::class));

    $event = Event::sole();
    expect($event->event_type_id)->toBe('commit_pushed')
        ->and($event->user_id)->toBe($user->id)
        ->and($event->title)->toBe('fix login')
        ->and($event->description)->toBe('reset the session')
        ->and($event->customer_id)->toEqual($customer->id)
        ->and($event->started_at)->toBeNull()
        ->and($event->ended_at->toIso8601String())->toBe('2026-09-05T09:38:30+00:00')
        ->and($event->external_id)->toBe('acme/api@abc123');
});

it('learns the github login of the commit author', function () {
    EventFacade::fake();
    $user = User::factory()->create(['email' => 'dev@example.com', 'github_login' => null]);
    $payload = [
        'ref' => 'refs/heads/main',
        'repository' => ['full_name' => 'acme/api'],
        'commits' => [[
            'id' => 'abc123',
            'message' => 'fix login',
            'timestamp' => '2026-09-05T09:38:30+00:00',
            'author' => ['email' => 'dev@example.com', 'username' => 'octocat'],
        ]],
    ];

    new ProcessWebhookJob($payload, null, 'push')->handle(app(TicketService::class));

    expect($user->refresh()->github_login)->toBe('octocat');
});

it('ignores a commit whose author is not a Timatic user', function () {
    EventFacade::fake();
    $payload = [
        'ref' => 'refs/heads/main',
        'repository' => ['full_name' => 'acme/api'],
        'commits' => [[
            'id' => 'abc123',
            'message' => 'fix login',
            'timestamp' => '2026-09-05T09:38:30+00:00',
            'author' => ['email' => 'stranger@example.com', 'username' => 'stranger'],
        ]],
    ];

    new ProcessWebhookJob($payload, null, 'push')->handle(app(TicketService::class));

    expect(Event::count())->toBe(0);
});

it('does not create a second event when the same commit is delivered again', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $payload = [
        'ref' => 'refs/heads/main',
        'repository' => ['full_name' => 'acme/api'],
        'commits' => [[
            'id' => 'abc123',
            'message' => 'fix login',
            'timestamp' => '2026-09-05T09:38:30+00:00',
            'author' => ['email' => 'dev@example.com', 'username' => 'octocat'],
        ]],
    ];

    new ProcessWebhookJob($payload, null, 'push')->handle(app(TicketService::class));
    new ProcessWebhookJob($payload, null, 'push')->handle(app(TicketService::class));

    expect(Event::count())->toBe(1);
});

it('records a rebase when a forced push replays known commits with rewritten dates', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $original = [
        'ref' => 'refs/heads/feature/login',
        'repository' => ['full_name' => 'acme/api'],
        'commits' => [[
            'id' => 'abc123',
            'message' => 'fix login',
            'timestamp' => '2026-09-05T09:38:30+00:00',
            'author' => ['email' => 'dev@example.com', 'username' => 'octocat'],
        ]],
    ];
    $forced = [
        'ref' => 'refs/heads/feature/login',
        'forced' => true,
        'repository' => ['full_name' => 'acme/api'],
        'commits' => [[
            'id' => 'def456',
            'message' => 'fix login',
            'timestamp' => '2026-09-05T14:00:00+00:00',
            'author' => ['email' => 'dev@example.com', 'username' => 'octocat'],
        ]],
    ];

    new ProcessWebhookJob($original, null, 'push')->handle(app(TicketService::class));
    new ProcessWebhookJob($forced, null, 'push')->handle(app(TicketService::class));

    expect(Event::where('event_type_id', 'commit_pushed')->count())->toBe(1)
        ->and(Event::where('event_type_id', 'rebase')->sole()->title)
        ->toBe('Rebased 1 commits on feature/login');
});
