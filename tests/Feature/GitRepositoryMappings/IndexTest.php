<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;
use Timatic\Bitbucket\Models\RepositoryMapping;

uses(LoginUser::class);
uses(RefreshDatabase::class);

it('returns the customer and budget a git remote is mapped to', function () {
    // Given
    $this->loginUser();
    /** @var Budget $budget */
    $budget = Budget::factory()->create();
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'workspace_slug' => 'acme',
        'repository_slug' => 'shop',
        'repository_name' => 'shop',
        'customer_id' => $budget->customer_id,
        'budget_id' => $budget->id,
    ]);

    // When
    $response = $this->get('git-repository-mappings?filter[remote]='.urlencode('git@bitbucket.org:acme/shop.git'));

    // Then
    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe('bitbucket.org/acme/shop')
        ->and($response->json('data.0.type'))->toBe('git-repository-mappings')
        ->and($response->json('data.0.attributes.customerId'))->toBe((string) $budget->customer_id)
        ->and($response->json('data.0.attributes.budgetId'))->toBe($budget->id);
});

it('returns nothing for a repository Timatic does not map', function () {
    // Given
    $this->loginUser();

    // When
    $response = $this->get('git-repository-mappings?filter[remote]='.urlencode('https://bitbucket.org/acme/unknown.git'));

    // Then
    $response->assertSuccessful();
    expect($response->json('data'))->toBe([]);
});
