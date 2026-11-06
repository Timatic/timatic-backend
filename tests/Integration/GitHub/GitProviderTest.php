<?php

declare(strict_types=1);

use App\DataTransferObjects\GitRemote;
use App\Integrations\Git\GitRepositoryService;
use App\Models\Budget;
use App\Models\Customer;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Timatic\GitHub\Models\RepositoryMapping;

uses(RefreshDatabase::class);

it('finds the customer and budget a GitHub repository is mapped to', function () {
    // Given
    /** @var Budget $budget */
    $budget = Budget::factory()->create();
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => []]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'Shop',
        'repository_full_name' => 'acme/Shop',
        'customer_id' => $budget->customer_id,
        'budget_id' => $budget->id,
    ]);

    // When
    $mapping = app(GitRepositoryService::class)->findMappingByRemote(GitRemote::fromUrl('https://github.com/acme/shop.git'));

    // Then
    expect($mapping?->repository)->toBe('github.com/acme/shop')
        ->and($mapping?->customer_id)->toBe($budget->customer_id)
        ->and($mapping?->budget_id)->toBe($budget->id);
});

it('ignores an archived GitHub repository', function () {
    // Given
    /** @var Customer $customer */
    $customer = Customer::factory()->create();
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => []]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'shop',
        'repository_full_name' => 'acme/shop',
        'customer_id' => $customer->id,
        'is_archived' => true,
    ]);

    // When
    $mapping = app(GitRepositoryService::class)->findMappingByRemote(GitRemote::fromUrl('git@github.com:acme/shop.git'));

    // Then
    expect($mapping)->toBeNull();
});
