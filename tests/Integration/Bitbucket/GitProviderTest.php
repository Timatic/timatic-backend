<?php

declare(strict_types=1);

use App\DataTransferObjects\GitRemote;
use App\Integrations\GitRepositoryService;
use App\Models\Budget;
use App\Models\Customer;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Timatic\Bitbucket\Models\RepositoryMapping;

uses(RefreshDatabase::class);

it('finds the customer and budget a Bitbucket repository is mapped to', function () {
    // Given
    /** @var Budget $budget */
    $budget = Budget::factory()->create();
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'workspace_slug' => 'acme',
        'repository_slug' => 'Shop',
        'repository_name' => 'Shop',
        'customer_id' => $budget->customer_id,
        'budget_id' => $budget->id,
    ]);

    // When
    $mapping = app(GitRepositoryService::class)->findMappingByRemote(GitRemote::fromUrl('git@bitbucket.org:acme/shop.git'));

    // Then
    expect($mapping?->repository)->toBe('bitbucket.org/acme/shop')
        ->and($mapping?->customer_id)->toBe($budget->customer_id)
        ->and($mapping?->budget_id)->toBe($budget->id);
});

it('ignores an archived Bitbucket repository', function () {
    // Given
    /** @var Customer $customer */
    $customer = Customer::factory()->create();
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'workspace_slug' => 'acme',
        'repository_slug' => 'shop',
        'repository_name' => 'shop',
        'customer_id' => $customer->id,
        'is_archived' => true,
    ]);

    // When
    $mapping = app(GitRepositoryService::class)->findMappingByRemote(GitRemote::fromUrl('git@bitbucket.org:acme/shop.git'));

    // Then
    expect($mapping)->toBeNull();
});
