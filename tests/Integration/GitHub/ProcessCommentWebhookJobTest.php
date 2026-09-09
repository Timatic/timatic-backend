<?php

use App\Integrations\TicketService;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;
use Timatic\GitHub\Jobs\ProcessWebhookJob;

it('creates an issue_commented event for a comment on an issue', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => 'created',
        'sender' => ['login' => 'octocat'],
        'repository' => ['full_name' => 'acme/api'],
        'issue' => ['number' => 42, 'title' => 'Login fails on Safari'],
        'comment' => ['id' => 9001, 'body' => 'Reproduced on 17.4', 'created_at' => '2026-09-05T15:00:00Z'],
    ];

    new ProcessWebhookJob($payload, null, 'issue_comment', 'created')->handle(app(TicketService::class));

    $event = Event::sole();
    expect($event->event_type_id)->toBe('issue_commented')
        ->and($event->title)->toBe('Login fails on Safari')
        ->and($event->description)->toBe('Reproduced on 17.4');
});

it('creates a pr_commented event for a comment on a pull request', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => 'created',
        'sender' => ['login' => 'octocat'],
        'repository' => ['full_name' => 'acme/api'],
        'issue' => [
            'number' => 43,
            'title' => 'Fix login',
            'pull_request' => ['url' => 'https://api.github.com/repos/acme/api/pulls/43'],
        ],
        'comment' => ['id' => 9002, 'body' => 'Ship it', 'created_at' => '2026-09-05T15:30:00Z'],
    ];

    new ProcessWebhookJob($payload, null, 'issue_comment', 'created')->handle(app(TicketService::class));

    expect(Event::sole()->event_type_id)->toBe('pr_commented');
});

it('ignores an edited comment', function () {
    EventFacade::fake();
    User::factory()->create(['github_login' => 'octocat']);
    $payload = [
        'action' => 'edited',
        'sender' => ['login' => 'octocat'],
        'repository' => ['full_name' => 'acme/api'],
        'issue' => ['number' => 42, 'title' => 'Login fails on Safari'],
        'comment' => ['id' => 9001, 'body' => 'Reproduced on 17.4', 'created_at' => '2026-09-05T15:00:00Z'],
    ];

    new ProcessWebhookJob($payload, null, 'issue_comment', 'edited')->handle(app(TicketService::class));

    expect(Event::count())->toBe(0);
});
