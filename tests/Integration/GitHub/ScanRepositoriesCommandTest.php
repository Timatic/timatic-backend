<?php

use App\Models\Customer;
use App\Models\Integration;
use App\Models\Repository;
use App\Models\User;
use Carbon\Carbon;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Timatic\GitHub\Models\RepositoryMapping;
use Timatic\GitHub\Requests\GetInstallationRepositoriesRequest;
use Timatic\GitHub\Requests\GetRepositoryCommitsRequest;

afterEach(function () {
    MockClient::destroyGlobal();
});

it('records the latest commit of a Timatic user in recently pushed repositories without a customer', function () {
    // Given
    $this->travelTo('2026-06-05 07:30:00');
    User::factory()->create(['email' => 'dev@example.com']);
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'shop',
        'repository_full_name' => 'acme/shop',
        'customer_id' => Customer::factory()->create()->id,
    ]);
    Repository::factory()->create(['integration_id' => $integration->id, 'identifier' => 'acme/dotfiles', 'is_ignored' => true]);
    $commit = fn (string $email, string $date): array => ['sha' => md5($email.$date), 'author' => null, 'commit' => ['author' => ['email' => $email, 'date' => $date], 'committer' => ['date' => $date]]];
    MockClient::global([
        GetInstallationRepositoriesRequest::class => MockResponse::make([
            'total_count' => 5,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme'], 'pushed_at' => '2026-06-04T18:00:00Z'],
                ['id' => 2, 'name' => 'shop', 'full_name' => 'acme/shop', 'owner' => ['login' => 'acme'], 'pushed_at' => '2026-06-04T16:12:00Z'],
                ['id' => 3, 'name' => 'dotfiles', 'full_name' => 'acme/dotfiles', 'owner' => ['login' => 'acme'], 'pushed_at' => '2026-06-04T16:12:00Z'],
                ['id' => 4, 'name' => 'legacy', 'full_name' => 'acme/legacy', 'owner' => ['login' => 'acme'], 'pushed_at' => '2025-01-10T09:00:00Z'],
                ['id' => 5, 'name' => 'fork', 'full_name' => 'acme/fork', 'owner' => ['login' => 'acme'], 'pushed_at' => '2026-06-04T16:12:00Z'],
            ],
        ]),
        GetRepositoryCommitsRequest::class => fn (PendingRequest $request): MockResponse => MockResponse::make(match (true) {
            str_contains($request->getUrl(), 'acme/api') => [
                $commit('stranger@example.org', '2026-06-04T18:00:00Z'),
                $commit('dev@example.com', '2026-06-04T16:12:00Z'),
                $commit('dev@example.com', '2026-06-03T09:00:00Z'),
            ],
            str_contains($request->getUrl(), 'acme/fork') => [
                $commit('stranger@example.org', '2026-06-04T16:12:00Z'),
            ],
        }),
    ]);

    // When
    $this->artisan('github:scan-repositories')->assertSuccessful();

    // Then
    expect(Repository::whereNotNull('last_work_at')->get()->map->only('identifier', 'last_work_at')->all())->toEqual([
        ['identifier' => 'acme/api', 'last_work_at' => Carbon::parse('2026-06-04 16:12:00')],
    ]);
});

it('reports a failing integration and scans the next', function () {
    // Given
    $this->travelTo('2026-06-05 07:30:00');
    User::factory()->create(['email' => 'dev@example.com']);
    Integration::create(['title' => 'Broken', 'type' => 'github', 'config' => ['installations' => [['id' => 1111, 'account' => 'broken']]]]);
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    Cache::put('github.installation_token.1111', 'ghs_broken_token');
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    MockClient::global([
        GetInstallationRepositoriesRequest::class => fn (PendingRequest $request): MockResponse => MockResponse::make([
            'total_count' => 1,
            'repositories' => [
                str_contains((string) $request->headers()->get('Authorization'), 'broken')
                    ? ['id' => 1, 'name' => 'site', 'full_name' => 'broken/site', 'owner' => ['login' => 'broken'], 'pushed_at' => '2026-06-04T16:12:00Z']
                    : ['id' => 2, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme'], 'pushed_at' => '2026-06-04T16:12:00Z'],
            ],
        ]),
        GetRepositoryCommitsRequest::class => fn (PendingRequest $request): MockResponse => str_contains($request->getUrl(), 'broken/site')
            ? MockResponse::make(['message' => 'Server Error'], 500)
            : MockResponse::make([['sha' => 'abc', 'author' => null, 'commit' => ['author' => ['email' => 'dev@example.com', 'date' => '2026-06-04T16:12:00Z'], 'committer' => ['date' => '2026-06-04T16:12:00Z']]]]),
    ]);

    // When
    $this->artisan('github:scan-repositories')->assertSuccessful();

    // Then
    expect(Repository::pluck('identifier', 'integration_id')->all())->toBe([$integration->id => 'acme/api']);
});
