<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Repository;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Concerns\LoginUser;
use Timatic\GitHub\Filament\Pages\RepositoryMappingPage;
use Timatic\GitHub\Models\RepositoryMapping;
use Timatic\GitHub\Requests\CreateInstallationTokenRequest;
use Timatic\GitHub\Requests\CreateRepositoryWebhookRequest;
use Timatic\GitHub\Requests\DeleteRepositoryWebhookRequest;
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
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
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

    expect(RepositoryMapping::active()->pluck('repository_full_name')->all())->toBe(['acme/api', 'acme/web'])
        ->and(Repository::count())->toBe(0)
        ->and(RepositoryMapping::where('repository_full_name', 'acme/api')->sole())
        ->owner_login->toBe('acme')
        ->repository_name->toBe('api')
        ->installation_id->toBe(4242);
});

it('warns instead of failing when GitHub rejects the repository request', function () {
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
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
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
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
        CreateRepositoryWebhookRequest::class => MockResponse::make(['id' => 987654], 201),
    ]);

    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])
        ->callTableBulkAction('assign', [$mapping->id], [
            'customer_id' => $customer->id,
            'budget_id' => $budget->id,
        ]);

    expect($mapping->refresh())
        ->customer_id->toBe($customer->id)
        ->budget_id->toBe($budget->id)
        ->webhook_id->toBe(987654)
        ->and($integration->refresh()->config['webhook_secret'])->toBeString();
});

it('links a single repository from its row', function () {
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    $customer = Customer::factory()->create();
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
        CreateRepositoryWebhookRequest::class => MockResponse::make(['id' => 987654], 201),
    ]);

    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])
        ->callAction(TestAction::make('assign')->table($mapping), [
            'customer_id' => $customer->id,
            'budget_id' => null,
        ]);

    expect($mapping->refresh())
        ->customer_id->toBe($customer->id)
        ->webhook_id->toBe(987654);
});

it('removes the webhook of a repository that is no longer linked', function () {
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    $mapping = RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
        'customer_id' => Customer::factory()->create()->id,
        'webhook_id' => 987654,
    ]);
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    $mockClient = MockClient::global([
        GetInstallationRepositoriesRequest::class => MockResponse::make([
            'total_count' => 1,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme'], 'archived' => false],
            ],
        ]),
        DeleteRepositoryWebhookRequest::class => MockResponse::make('', 204),
    ]);

    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])
        ->removeTableFilter('unmapped')
        ->callTableBulkAction('assign', [$mapping->id], [
            'customer_id' => null,
            'budget_id' => null,
        ]);

    $mockClient->assertSent(fn ($request) => $request instanceof DeleteRepositoryWebhookRequest
        && $request->resolveEndpoint() === '/repos/acme/api/hooks/987654');
    expect($mapping->refresh())
        ->customer_id->toBeNull()
        ->webhook_id->toBeNull();
});

it('syncs the repositories of every adopted installation', function () {
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => [
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
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => [
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

    expect(Repository::count())->toBe(0);
});

it('searches the repository given in the search query parameter', function () {
    // Given
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    MockClient::global([
        GetInstallationRepositoriesRequest::class => MockResponse::make([
            'total_count' => 2,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme'], 'archived' => false],
                ['id' => 2, 'name' => 'web', 'full_name' => 'acme/web', 'owner' => ['login' => 'acme'], 'archived' => false],
            ],
        ]),
    ]);

    // When
    $page = Livewire::withQueryParams(['search' => 'acme/api'])->test(RepositoryMappingPage::class, ['record' => $integration->id]);

    // Then
    $page->assertCanSeeTableRecords(RepositoryMapping::where('repository_full_name', 'acme/api')->get())
        ->assertCanNotSeeTableRecords(RepositoryMapping::where('repository_full_name', 'acme/web')->get());
});

it('ignores a repository from its row', function () {
    // Given
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    MockClient::global([
        GetInstallationRepositoriesRequest::class => MockResponse::make([
            'total_count' => 1,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme'], 'archived' => false],
            ],
        ]),
    ]);
    $page = Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id]);

    // When
    $page->callAction(TestAction::make('ignore')->table(RepositoryMapping::sole()));

    // Then
    expect(Repository::sole()->only('integration_id', 'identifier', 'is_ignored'))
        ->toBe(['integration_id' => $integration->id, 'identifier' => 'acme/api', 'is_ignored' => true]);
});

it('ignores selected repositories', function () {
    // Given
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    MockClient::global([
        GetInstallationRepositoriesRequest::class => MockResponse::make([
            'total_count' => 1,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme']],
            ],
        ]),
    ]);
    $page = Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id]);

    // When
    $page->callTableBulkAction('ignore', [RepositoryMapping::sole()->id]);

    // Then
    expect(Repository::sole()->only('identifier', 'is_ignored'))->toBe(['identifier' => 'acme/api', 'is_ignored' => true]);
});

it('stops ignoring a repository once it is linked to a customer', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => ['installations' => [['id' => 4242, 'account' => 'acme']]]]);
    $customer = Customer::factory()->create();
    $mapping = RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
    ]);
    $mapping->ignore();
    Cache::put('github.installation_token.4242', 'ghs_installation_token');
    MockClient::global([
        GetInstallationRepositoriesRequest::class => MockResponse::make([
            'total_count' => 1,
            'repositories' => [
                ['id' => 1, 'name' => 'api', 'full_name' => 'acme/api', 'owner' => ['login' => 'acme']],
            ],
        ]),
        CreateRepositoryWebhookRequest::class => MockResponse::make(['id' => 987654], 201),
    ]);

    // When
    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])
        ->filterTable('is_ignored', true)
        ->callAction(TestAction::make('assign')->table($mapping), [
            'customer_id' => $customer->id,
            'budget_id' => null,
        ])
        ->assertNotified('Repository gekoppeld');

    // Then
    expect(Repository::sole()->is_ignored)->toBeFalse();
});
