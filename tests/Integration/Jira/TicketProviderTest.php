<?php

use App\Models\Customer;
use App\Models\Integration;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\Requests\GetIssueRequest;
use Timatic\Jira\TicketProvider;

afterEach(function () {
    MockClient::destroyGlobal();
});

it('fetches ticket details with comments and status changes as actions', function () {
    $customer = Customer::factory()->create();
    ProjectMapping::create(['integration_id' => Integration::factory()->create(['type' => 'jira'])->id, 'project_key' => 'ACME', 'project_name' => 'Acme', 'customer_id' => $customer->id, 'is_archived' => false]);
    MockClient::global([
        GetIssueRequest::class => MockResponse::make([
            'key' => 'ACME-12',
            'fields' => [
                'summary' => 'Login fails on Safari',
                'created' => '2026-09-01T10:00:00.000+0000',
                'resolutiondate' => '2026-09-05T16:00:00.000+0000',
                'issuetype' => ['name' => 'Bug'],
                'comment' => ['comments' => [
                    ['id' => '100', 'author' => ['displayName' => 'Ann', 'emailAddress' => 'ann@example.com'], 'body' => ['type' => 'doc'], 'created' => '2026-09-03T09:00:00.000+0000'],
                    ['id' => '101', 'author' => ['displayName' => 'Bob'], 'body' => ['type' => 'doc'], 'created' => '2026-09-04T09:00:00.000+0000'],
                ]],
            ],
            'renderedFields' => ['comment' => ['comments' => [
                ['id' => '100', 'body' => '<p>Reproduced it</p>'],
                ['id' => '101', 'body' => '<p> </p>'],
            ]]],
            'changelog' => ['histories' => [
                ['id' => '500', 'author' => ['displayName' => 'Bob', 'emailAddress' => 'bob@example.com'], 'created' => '2026-09-02T09:00:00.000+0000', 'items' => [
                    ['field' => 'assignee', 'fromString' => null, 'toString' => 'Bob'],
                    ['field' => 'status', 'fromString' => 'To Do', 'toString' => 'In Progress'],
                ]],
            ]],
        ]),
    ]);

    $ticket = TicketProvider::fromConfig(['cloud_id' => 'cloud-1', 'access_token' => 'token', 'cloud_url' => 'https://acme.atlassian.net'])
        ->fetchTicketDetails('acme-12');

    expect($ticket->type)->toBe('Bug')
        ->and($ticket->number)->toBe('ACME-12')
        ->and($ticket->title)->toBe('Login fails on Safari')
        ->and($ticket->created_at->toIso8601String())->toBe('2026-09-01T10:00:00+00:00')
        ->and($ticket->closed_at?->toIso8601String())->toBe('2026-09-05T16:00:00+00:00')
        ->and($ticket->url)->toBe('https://acme.atlassian.net/browse/ACME-12')
        ->and($ticket->customer_id)->toBe($customer->id)
        ->and($ticket->actions->map(fn ($action) => [$action->id, $action->text, $action->personDisplayName, $action->personEmail])->all())->toBe([
            ['changelog-500-1', "Status changed from 'To Do' to 'In Progress'", 'Bob', 'bob@example.com'],
            ['comment-100', 'Reproduced it', 'Ann', 'ann@example.com'],
        ]);
});

it('returns no ticket details when the issue does not exist', function () {
    MockClient::global([
        GetIssueRequest::class => MockResponse::make(['errorMessages' => ['Issue does not exist']], 404),
    ]);

    $ticket = TicketProvider::fromConfig(['cloud_id' => 'cloud-1', 'access_token' => 'token'])
        ->fetchTicketDetails('ACME-404');

    expect($ticket)->toBeNull();
});
