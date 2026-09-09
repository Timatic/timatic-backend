<?php

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GitHub\Connector;
use Timatic\GitHub\DataTransferObjects\GitHubInstallation;
use Timatic\GitHub\DataTransferObjects\GitHubIssue;
use Timatic\GitHub\DataTransferObjects\GitHubRepositoryPage;
use Timatic\GitHub\Requests\GetInstallationRepositoriesRequest;
use Timatic\GitHub\Requests\GetIssueCommentsRequest;
use Timatic\GitHub\Requests\GetIssuesRequest;
use Timatic\GitHub\Requests\GetUserInstallationsRequest;
use Timatic\GitHub\Requests\SearchIssuesRequest;

afterEach(function () {
    MockClient::destroyGlobal();
});

it('maps the installations of the authenticated user', function () {
    MockClient::global([
        GetUserInstallationsRequest::class => MockResponse::make([
            'total_count' => 1,
            'installations' => [
                ['id' => 4242, 'account' => ['login' => 'acme', 'type' => 'Organization']],
            ],
        ]),
    ]);

    /** @var array<int, GitHubInstallation> $installations */
    $installations = Connector::forUser('ghu_user_token')->send(new GetUserInstallationsRequest)->dto();

    expect($installations[0]->id)->toBe(4242)
        ->and($installations[0]->accountLogin)->toBe('acme')
        ->and($installations[0]->accountType)->toBe('Organization');
});

it('maps a page of installation repositories', function () {
    MockClient::global([
        GetInstallationRepositoriesRequest::class => MockResponse::make([
            'total_count' => 2,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme'], 'archived' => false],
                ['id' => 2, 'name' => 'web', 'full_name' => 'acme/web', 'owner' => ['login' => 'acme'], 'archived' => true],
            ],
        ]),
    ]);

    /** @var GitHubRepositoryPage $page */
    $page = Connector::forUser('ghs_installation_token')->send(new GetInstallationRepositoriesRequest)->dto();

    expect($page->totalCount)->toBe(2)
        ->and($page->repositories[0]->fullName)->toBe('acme/api')
        ->and($page->repositories[0]->isArchived)->toBeFalse()
        ->and($page->repositories[1]->isArchived)->toBeTrue();
});

it('maps repository issues and marks pull requests', function () {
    MockClient::global([
        GetIssuesRequest::class => MockResponse::make([
            [
                'number' => 42,
                'title' => 'Login fails on Safari',
                'html_url' => 'https://github.com/acme/api/issues/42',
                'state' => 'open',
                'repository_url' => 'https://api.github.com/repos/acme/api',
                'created_at' => '2026-09-01T10:00:00Z',
                'closed_at' => null,
            ],
            [
                'number' => 43,
                'title' => 'Fix login',
                'html_url' => 'https://github.com/acme/api/pull/43',
                'state' => 'closed',
                'repository_url' => 'https://api.github.com/repos/acme/api',
                'created_at' => '2026-09-02T10:00:00Z',
                'closed_at' => '2026-09-03T10:00:00Z',
                'pull_request' => ['url' => 'https://api.github.com/repos/acme/api/pulls/43'],
            ],
        ]),
    ]);

    /** @var array<int, GitHubIssue> $issues */
    $issues = Connector::forUser('ghs_installation_token')->send(new GetIssuesRequest('acme', 'api'))->dto();

    expect($issues[0]->key())->toBe('acme/api#42')
        ->and($issues[0]->title)->toBe('Login fails on Safari')
        ->and($issues[0]->isPullRequest)->toBeFalse()
        ->and($issues[0]->closedAt)->toBeNull()
        ->and($issues[1]->isPullRequest)->toBeTrue()
        ->and($issues[1]->closedAt->toDateString())->toBe('2026-09-03');
});

it('maps search results into issues', function () {
    MockClient::global([
        SearchIssuesRequest::class => MockResponse::make([
            'total_count' => 1,
            'items' => [
                [
                    'number' => 7,
                    'title' => 'Add export',
                    'html_url' => 'https://github.com/acme/web/issues/7',
                    'state' => 'open',
                    'repository_url' => 'https://api.github.com/repos/acme/web',
                    'created_at' => '2026-09-01T10:00:00Z',
                ],
            ],
        ]),
    ]);

    /** @var array<int, GitHubIssue> $issues */
    $issues = Connector::forUser('ghs_installation_token')->send(new SearchIssuesRequest('is:issue repo:acme/web'))->dto();

    expect($issues[0]->key())->toBe('acme/web#7');
});

it('maps issue comments', function () {
    MockClient::global([
        GetIssueCommentsRequest::class => MockResponse::make([
            ['id' => 555, 'user' => ['login' => 'octocat'], 'body' => "  Looks good\n", 'created_at' => '2026-09-04T09:00:00Z'],
        ]),
    ]);

    $comments = Connector::forUser('ghs_installation_token')->send(new GetIssueCommentsRequest('acme', 'api', 42))->dto();

    expect($comments[0]->id)->toBe(555)
        ->and($comments[0]->authorLogin)->toBe('octocat')
        ->and($comments[0]->body)->toBe('Looks good');
});
