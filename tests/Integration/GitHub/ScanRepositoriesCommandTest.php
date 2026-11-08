<?php

use App\Models\Customer;
use App\Models\Integration;
use App\Models\Repository;
use Carbon\Carbon;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GitHub\Models\RepositoryMapping;
use Timatic\GitHub\Requests\GetInstallationRepositoriesRequest;

afterEach(function () {
    MockClient::destroyGlobal();
});

it('records recent pushes to repositories without a customer', function () {
    // Given
    $this->travelTo('2026-06-05 07:30:00');
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
    MockClient::global([
        GetInstallationRepositoriesRequest::class => MockResponse::make([
            'total_count' => 4,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme'], 'archived' => false, 'pushed_at' => '2026-06-04T16:12:00Z'],
                ['id' => 2, 'name' => 'shop', 'full_name' => 'acme/shop', 'owner' => ['login' => 'acme'], 'archived' => false, 'pushed_at' => '2026-06-04T16:12:00Z'],
                ['id' => 3, 'name' => 'dotfiles', 'full_name' => 'acme/dotfiles', 'owner' => ['login' => 'acme'], 'archived' => false, 'pushed_at' => '2026-06-04T16:12:00Z'],
                ['id' => 4, 'name' => 'legacy', 'full_name' => 'acme/legacy', 'owner' => ['login' => 'acme'], 'archived' => false, 'pushed_at' => '2025-01-10T09:00:00Z'],
            ],
        ]),
    ]);

    // When
    $this->artisan('github:scan-repositories')->assertSuccessful();

    // Then
    expect(Repository::whereNotNull('last_work_at')->get()->map->only('identifier', 'name', 'last_work_at')->all())->toEqual([
        ['identifier' => 'acme/api', 'name' => 'api', 'last_work_at' => Carbon::parse('2026-06-04 16:12:00')],
    ]);
});
