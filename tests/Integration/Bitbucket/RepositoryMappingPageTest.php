<?php

use App\Models\Customer;
use App\Models\Integration;
use App\Models\Repository;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Concerns\LoginUser;
use Timatic\Bitbucket\Filament\Pages\RepositoryMappingPage;
use Timatic\Bitbucket\Models\RepositoryMapping;
use Timatic\Bitbucket\Requests\GetRepositoriesRequest;

uses(LoginUser::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('links a single repository from its row', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => ['access_token' => 'token', 'workspace' => 'workspace', 'expires_at' => now()->addHour()->timestamp]]);
    $customer = Customer::factory()->create();
    $mapping = RepositoryMapping::create([
        'integration_id' => $integration->id,
        'workspace_slug' => 'workspace',
        'repository_slug' => 'repo',
        'repository_name' => 'Repo',
    ]);
    MockClient::global([
        GetRepositoriesRequest::class => MockResponse::make(['values' => [['slug' => 'repo', 'full_name' => 'workspace/repo', 'name' => 'Repo']]]),
    ]);

    // When
    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])
        ->callAction(TestAction::make('assign')->table($mapping), [
            'customer_id' => $customer->id,
            'budget_id' => null,
        ]);

    // Then
    expect($mapping->refresh()->customer_id)->toBe($customer->id);
});

it('searches the repository given in the search query parameter', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => ['access_token' => 'token', 'workspace' => 'workspace', 'expires_at' => now()->addHour()->timestamp]]);
    $repo = RepositoryMapping::create(['integration_id' => $integration->id, 'workspace_slug' => 'workspace', 'repository_slug' => 'repo', 'repository_name' => 'Repo']);
    $other = RepositoryMapping::create(['integration_id' => $integration->id, 'workspace_slug' => 'workspace', 'repository_slug' => 'other', 'repository_name' => 'Other']);
    MockClient::global([
        GetRepositoriesRequest::class => MockResponse::make(['values' => []]),
    ]);

    // When
    $page = Livewire::withQueryParams(['search' => 'repo'])->test(RepositoryMappingPage::class, ['record' => $integration->id]);

    // Then
    $page->assertCanSeeTableRecords([$repo])->assertCanNotSeeTableRecords([$other]);
});

it('stops ignoring a repository from its row', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => ['access_token' => 'token', 'workspace' => 'workspace', 'expires_at' => now()->addHour()->timestamp]]);
    $mapping = RepositoryMapping::create(['integration_id' => $integration->id, 'workspace_slug' => 'workspace', 'repository_slug' => 'repo', 'repository_name' => 'Repo']);
    $mapping->ignore();
    MockClient::global([
        GetRepositoriesRequest::class => MockResponse::make(['values' => []]),
    ]);

    // When
    Livewire::test(RepositoryMappingPage::class, ['record' => $integration->id])
        ->filterTable('is_ignored', true)
        ->callAction(TestAction::make('restore')->table($mapping));

    // Then
    expect(Repository::sole()->only('identifier', 'is_ignored'))->toBe(['identifier' => 'repo', 'is_ignored' => false]);
});
