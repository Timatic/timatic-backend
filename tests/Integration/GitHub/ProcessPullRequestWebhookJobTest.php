<?php

use App\Integrations\TicketService;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;
use Timatic\GitHub\Jobs\ProcessWebhookJob;

it('creates a pr_opened event when a pull request is opened', function () {
    EventFacade::fake();
    $user = User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => 'opened',
        'sender' => ['login' => 'octocat'],
        'repository' => ['full_name' => 'acme/api'],
        'pull_request' => [
            'id' => 7001,
            'title' => 'Fix login',
            'head' => ['ref' => 'feature/login'],
            'created_at' => '2026-09-05T09:00:00Z',
            'updated_at' => '2026-09-05T09:00:00Z',
            'merged' => false,
        ],
    ];

    new ProcessWebhookJob($payload, null, 'pull_request', 'opened')->handle(app(TicketService::class));

    $event = Event::sole();
    expect($event->event_type_id)->toBe('pr_opened')
        ->and($event->user_id)->toBe($user->id)
        ->and($event->title)->toBe('Fix login')
        ->and($event->external_id)->toBe('pr:pr_opened:7001:2026-09-05T09:00:00Z');
});

it('creates a pr_merged event when a merged pull request closes', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => 'closed',
        'sender' => ['login' => 'octocat'],
        'repository' => ['full_name' => 'acme/api'],
        'pull_request' => [
            'id' => 7001,
            'title' => 'Fix login',
            'head' => ['ref' => 'feature/login'],
            'updated_at' => '2026-09-05T12:00:00Z',
            'merged' => true,
        ],
    ];

    new ProcessWebhookJob($payload, null, 'pull_request', 'closed')->handle(app(TicketService::class));

    expect(Event::sole()->event_type_id)->toBe('pr_merged');
});

it('creates a pr_declined event when an unmerged pull request closes', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => 'closed',
        'sender' => ['login' => 'octocat'],
        'repository' => ['full_name' => 'acme/api'],
        'pull_request' => [
            'id' => 7001,
            'title' => 'Fix login',
            'head' => ['ref' => 'feature/login'],
            'updated_at' => '2026-09-05T12:00:00Z',
            'merged' => false,
        ],
    ];

    new ProcessWebhookJob($payload, null, 'pull_request', 'closed')->handle(app(TicketService::class));

    expect(Event::sole()->event_type_id)->toBe('pr_declined');
});

it('ignores a pull request event from an unknown github user', function () {
    EventFacade::fake();
    $payload = [
        'action' => 'opened',
        'sender' => ['login' => 'stranger'],
        'repository' => ['full_name' => 'acme/api'],
        'pull_request' => ['id' => 7001, 'title' => 'Fix login', 'updated_at' => '2026-09-05T09:00:00Z'],
    ];

    new ProcessWebhookJob($payload, null, 'pull_request', 'opened')->handle(app(TicketService::class));

    expect(Event::count())->toBe(0);
});

it('maps a submitted review to its approval state', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => 'submitted',
        'sender' => ['login' => 'octocat'],
        'repository' => ['full_name' => 'acme/api'],
        'pull_request' => ['id' => 7001, 'title' => 'Fix login', 'head' => ['ref' => 'feature/login']],
        'review' => [
            'id' => 8001,
            'state' => 'changes_requested',
            'body' => 'Please add a test',
            'submitted_at' => '2026-09-05T13:00:00Z',
        ],
    ];

    new ProcessWebhookJob($payload, null, 'pull_request_review', 'submitted')->handle(app(TicketService::class));

    $event = Event::sole();
    expect($event->event_type_id)->toBe('pr_changes_requested')
        ->and($event->description)->toBe('Please add a test')
        ->and($event->external_id)->toBe('review:8001');
});

it('creates a pr_commented event for a review comment', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => 'created',
        'sender' => ['login' => 'octocat'],
        'repository' => ['full_name' => 'acme/api'],
        'pull_request' => ['id' => 7001, 'title' => 'Fix login', 'head' => ['ref' => 'feature/login']],
        'comment' => ['id' => 9001, 'body' => 'Nitpick: naming', 'created_at' => '2026-09-05T14:00:00Z'],
    ];

    new ProcessWebhookJob($payload, null, 'pull_request_review_comment', 'created')->handle(app(TicketService::class));

    expect(Event::sole())
        ->event_type_id->toBe('pr_commented')
        ->external_id->toBe('comment:9001');
});
