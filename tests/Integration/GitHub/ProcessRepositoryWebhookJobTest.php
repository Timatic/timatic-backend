<?php

use App\Integrations\TicketService;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;
use Timatic\GitHub\Jobs\ProcessWebhookJob;

it('creates a repository_renamed event naming both repositories', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => 'renamed',
        'sender' => ['login' => 'octocat'],
        'repository' => ['id' => 1, 'full_name' => 'acme/api', 'updated_at' => '2026-09-05T16:00:00Z'],
        'changes' => ['repository' => ['name' => ['from' => 'backend']]],
    ];

    new ProcessWebhookJob($payload, null, 'repository', 'renamed')->handle(app(TicketService::class));

    $event = Event::sole();
    expect($event->event_type_id)->toBe('repository_renamed')
        ->and($event->title)->toBe('Repository renamed: backend to acme/api')
        ->and($event->external_id)->toBe('repository:renamed:1:2026-09-05T16:00:00Z');
});

it('creates an event per repository action', function (string $action) {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => $action,
        'sender' => ['login' => 'octocat'],
        'repository' => ['id' => 1, 'full_name' => 'acme/api', 'updated_at' => '2026-09-05T16:00:00Z'],
    ];

    new ProcessWebhookJob($payload, null, 'repository', $action)->handle(app(TicketService::class));

    expect(Event::sole()->event_type_id)->toBe('repository_'.$action);
})->with(['created', 'deleted', 'archived', 'unarchived', 'publicized', 'privatized', 'edited', 'transferred']);

it('ignores a repository action Timatic does not track', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => 'starred',
        'sender' => ['login' => 'octocat'],
        'repository' => ['id' => 1, 'full_name' => 'acme/api', 'updated_at' => '2026-09-05T16:00:00Z'],
    ];

    new ProcessWebhookJob($payload, null, 'repository', 'starred')->handle(app(TicketService::class));

    expect(Event::count())->toBe(0);
});

it('does not duplicate a redelivered repository event', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => 'archived',
        'sender' => ['login' => 'octocat'],
        'repository' => ['id' => 1, 'full_name' => 'acme/api', 'updated_at' => '2026-09-05T16:00:00Z'],
    ];

    new ProcessWebhookJob($payload, null, 'repository', 'archived')->handle(app(TicketService::class));
    new ProcessWebhookJob($payload, null, 'repository', 'archived')->handle(app(TicketService::class));

    expect(Event::count())->toBe(1);
});

it('creates a branch_created event for a new branch', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'ref' => 'feature/login',
        'ref_type' => 'branch',
        'sender' => ['login' => 'octocat'],
        'repository' => ['full_name' => 'acme/api'],
    ];

    new ProcessWebhookJob($payload, null, 'create')->handle(app(TicketService::class));

    $event = Event::sole();
    expect($event->event_type_id)->toBe('branch_created')
        ->and($event->title)->toBe('Created branch feature/login')
        ->and($event->external_id)->toBe('create:acme/api:feature/login');
});

it('creates a tag_created event for a new tag', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'ref' => 'v1.2.0',
        'ref_type' => 'tag',
        'sender' => ['login' => 'octocat'],
        'repository' => ['full_name' => 'acme/api'],
    ];

    new ProcessWebhookJob($payload, null, 'create')->handle(app(TicketService::class));

    expect(Event::sole())
        ->event_type_id->toBe('tag_created')
        ->title->toBe('Created tag v1.2.0');
});
