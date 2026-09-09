<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\Integration;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Concerns\LoginUser;
use Timatic\GitHub\Filament\Pages\RepositoryMappingPage;
use Timatic\GitHub\Models\RepositoryMapping;
use Timatic\GitHub\Requests\CreateInstallationTokenRequest;
use Timatic\GitHub\Requests\GetInstallationRepositoriesRequest;

uses(LoginUser::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('creates a mapping for every repository of the installation', function () {
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    MockClient::global([
        GetInstallationRepositoriesRequest::class => MockResponse::make([
            'total_count' => 2,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme'], 'archived' => false],
                ['id' => 2, 'name' => 'web', 'full_name' => 'acme/web', 'owner' => ['login' => 'acme'], 'archived' => true],
            ],
        ]),
    ]);

    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])->assertSuccessful();

    expect(RepositoryMapping::pluck('is_archived', 'repository_full_name')->all())
        ->toBe(['acme/api' => false, 'acme/web' => true])
        ->and(RepositoryMapping::where('repository_full_name', 'acme/api')->sole())
        ->owner_login->toBe('acme')
        ->repository_name->toBe('api')
        ->installation_id->toBe(4242);
});

it('archives a mapping whose repository is no longer accessible', function () {
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'legacy',
        'repository_full_name' => 'acme/legacy',
    ]);
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    MockClient::global([
        GetInstallationRepositoriesRequest::class => MockResponse::make([
            'total_count' => 1,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme'], 'archived' => false],
            ],
        ]),
    ]);

    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])->assertSuccessful();

    expect(RepositoryMapping::where('repository_full_name', 'acme/legacy')->sole()->is_archived)->toBeTrue();
});

it('warns instead of failing when GitHub rejects the repository request', function () {
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    MockClient::global([
        CreateInstallationTokenRequest::class => MockResponse::make(['message' => 'Bad credentials'], 401),
    ]);

    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])
        ->assertSuccessful()
        ->assertNotified(__('github::github.common.connection_expired_title'));

    expect(RepositoryMapping::count())->toBe(0);
});

it('links selected repositories to a customer and budget', function () {
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    $customer = Customer::factory()->create();
    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state(['effective_to' => null]))
        ->create(['customer_id' => $customer->id]);
    $mapping = RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
    ]);
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    MockClient::global([
        GetInstallationRepositoriesRequest::class => MockResponse::make([
            'total_count' => 1,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme'], 'archived' => false],
            ],
        ]),
    ]);

    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])
        ->callTableBulkAction('assign', [$mapping->id], [
            'customer_id' => $customer->id,
            'budget_id' => $budget->id,
        ]);

    expect($mapping->refresh())
        ->customer_id->toBe($customer->id)
        ->budget_id->toBe($budget->id);
});

it('syncs the repositories of every adopted installation', function () {
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'installations' => [['id' => 4242, 'account' => 'acme'], ['id' => 5353, 'account' => 'acme-labs']],
    ]]);
    Cache::put('github.installation_token.4242', 'ghs_acme_token');
    Cache::put('github.installation_token.5353', 'ghs_labs_token');
    MockClient::global([
        MockResponse::make([
            'total_count' => 1,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme'], 'archived' => false],
            ],
        ]),
        MockResponse::make([
            'total_count' => 1,
            'repositories' => [
                ['id' => 2, 'name' => 'site', 'full_name' => 'acme-labs/site', 'owner' => ['login' => 'acme-labs'], 'archived' => false],
            ],
        ]),
    ]);

    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])->assertSuccessful();

    expect(RepositoryMapping::pluck('installation_id', 'repository_full_name')->all())
        ->toBe(['acme/api' => 4242, 'acme-labs/site' => 5353]);
});

it('keeps the mappings of an installation that could not be reached', function () {
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'installations' => [['id' => 4242, 'account' => 'acme']],
    ]]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
    ]);
    MockClient::global([
        CreateInstallationTokenRequest::class => MockResponse::make(['message' => 'Bad credentials'], 401),
    ]);

    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])
        ->assertSuccessful()
        ->assertNotified(__('github::github.common.connection_expired_title'));

    expect(RepositoryMapping::sole()->is_archived)->toBeFalse();
});
