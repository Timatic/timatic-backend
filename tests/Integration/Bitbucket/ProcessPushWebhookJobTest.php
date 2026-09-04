<?php

use App\Integrations\TicketService;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Timatic\Bitbucket\Jobs\ProcessWebhookJob;
use Timatic\Bitbucket\Models\RepositoryMapping;

uses(RefreshDatabase::class);

/**
 * @param  list<array{message: string, date: string}>  $commits
 * @return array<string, mixed>
 */
function pushPayload(string $email, array $commits, bool $forced = false): array
{
    return [
        'push' => [
            'changes' => [[
                'new' => ['name' => 'feature/test'],
                'forced' => $forced,
                'commits' => array_map(fn (array $commit) => [
                    'hash' => fake()->sha1(),
                    'message' => $commit['message'],
                    'date' => $commit['date'],
                    'author' => ['raw' => 'Test User <'.$email.'>'],
                ], $commits),
            ]],
        ],
        'repository' => ['full_name' => 'workspace/repo'],
    ];
}

function pushMapping(): RepositoryMapping
{
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $integration = Integration::create(['name' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);

    return RepositoryMapping::create([
        'integration_id' => $integration->id,
        'workspace_slug' => 'workspace',
        'repository_slug' => 'repo',
        'repository_name' => 'repo',
        'customer_id' => $customer->id,
        'budget_id' => null,
    ]);
}

it('creates a commit_pushed event for a new commit', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $mapping = pushMapping();
    $payload = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
    ]);

    new ProcessWebhookJob($payload, $mapping, 'repo:push')->handle(app(TicketService::class));

    expect(Event::where('event_type_id', 'commit_pushed')->count())->toBe(1)
        ->and(Event::where('event_type_id', 'rebase')->count())->toBe(0);
});

it('stores a commit as a point event without a start time', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $mapping = pushMapping();
    $payload = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
    ]);

    new ProcessWebhookJob($payload, $mapping, 'repo:push')->handle(app(TicketService::class));

    $event = Event::sole();
    expect($event->started_at)->toBeNull()
        ->and($event->ended_at->toIso8601String())->toBe('2026-06-05T09:38:30+00:00');
});

it('creates one rebase event instead of duplicate commit events for known commits', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $mapping = pushMapping();
    $commits = [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
        ['message' => 'fix pest', 'date' => '2026-06-05T09:40:00+00:00'],
    ];

    new ProcessWebhookJob(pushPayload('dev@example.com', $commits), $mapping, 'repo:push')->handle(app(TicketService::class));
    new ProcessWebhookJob(pushPayload('dev@example.com', $commits), $mapping, 'repo:push')->handle(app(TicketService::class));

    expect(Event::where('event_type_id', 'commit_pushed')->count())->toBe(2)
        ->and(Event::where('event_type_id', 'rebase')->count())->toBe(1);
});

it('is a no-op when the exact same push webhook is redelivered', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $mapping = pushMapping();
    $payload = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
    ]);

    new ProcessWebhookJob($payload, $mapping, 'repo:push')->handle(app(TicketService::class));
    new ProcessWebhookJob($payload, $mapping, 'repo:push')->handle(app(TicketService::class));
    new ProcessWebhookJob($payload, $mapping, 'repo:push')->handle(app(TicketService::class));

    expect(Event::where('event_type_id', 'commit_pushed')->count())->toBe(1)
        ->and(Event::where('event_type_id', 'rebase')->count())->toBe(0);
});

it('creates events for new commits alongside a rebase event for known ones', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $mapping = pushMapping();
    $firstPush = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
    ]);
    $secondPush = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
        ['message' => 'fix phpstan', 'date' => '2026-06-05T10:00:00+00:00'],
    ]);

    new ProcessWebhookJob($firstPush, $mapping, 'repo:push')->handle(app(TicketService::class));
    new ProcessWebhookJob($secondPush, $mapping, 'repo:push')->handle(app(TicketService::class));

    expect(Event::where('event_type_id', 'commit_pushed')->count())->toBe(2)
        ->and(Event::where('event_type_id', 'rebase')->count())->toBe(1);
});

it('recognizes a rebased commit on a force-pushed branch by title even when the date changed', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $mapping = pushMapping();
    $originalPush = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T09:38:30+00:00'],
    ]);
    $forcedPush = pushPayload('dev@example.com', [
        ['message' => 'add vite', 'date' => '2026-06-05T11:00:00+00:00'],
    ], forced: true);

    new ProcessWebhookJob($originalPush, $mapping, 'repo:push')->handle(app(TicketService::class));
    new ProcessWebhookJob($forcedPush, $mapping, 'repo:push')->handle(app(TicketService::class));

    expect(Event::where('event_type_id', 'commit_pushed')->count())->toBe(1)
        ->and(Event::where('event_type_id', 'rebase')->count())->toBe(1);
});

it('creates a new commit_pushed event for a recurring commit title with a different date on a normal push', function () {
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $mapping = pushMapping();
    $firstPush = pushPayload('dev@example.com', [
        ['message' => 'composer update', 'date' => '2026-06-05T09:38:30+00:00'],
    ]);
    $secondPush = pushPayload('dev@example.com', [
        ['message' => 'composer update', 'date' => '2026-06-06T09:38:30+00:00'],
    ]);

    new ProcessWebhookJob($firstPush, $mapping, 'repo:push')->handle(app(TicketService::class));
    new ProcessWebhookJob($secondPush, $mapping, 'repo:push')->handle(app(TicketService::class));

    expect(Event::where('event_type_id', 'commit_pushed')->count())->toBe(2)
        ->and(Event::where('event_type_id', 'rebase')->count())->toBe(0);
});
