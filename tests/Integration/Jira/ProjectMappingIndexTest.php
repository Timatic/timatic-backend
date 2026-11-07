<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Models\ProjectMapping;

uses(LoginUser::class);
uses(RefreshDatabase::class);

it('lists the customer and budget every active Jira project is mapped to', function () {
    // Given
    $this->loginUser();
    /** @var Budget $budget */
    $budget = Budget::factory()->create();
    $mapping = ProjectMapping::query()->create([
        'integration_id' => Integration::factory()->create(['type' => 'jira'])->id,
        'project_key' => 'PIO',
        'project_name' => 'Pionect',
        'customer_id' => $budget->customer_id,
        'budget_id' => $budget->id,
    ]);
    ProjectMapping::query()->create([
        'integration_id' => $mapping->integration_id,
        'project_key' => 'OLD',
        'project_name' => 'Archived project',
        'is_archived' => true,
    ]);

    // When
    $response = $this->get('jira/project-mappings');

    // Then
    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe((string) $mapping->id)
        ->and($response->json('data.0.type'))->toBe('jira-project-mappings')
        ->and($response->json('data.0.attributes'))->toBe([
            'projectKey' => 'PIO',
            'projectName' => 'Pionect',
            'customerId' => (string) $budget->customer_id,
            'budgetId' => $budget->id,
        ]);
});
