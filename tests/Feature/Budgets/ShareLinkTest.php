<?php

declare(strict_types=1);

use App\Models\BudgetVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('creates a share link and stores only the hash of its token', function () {
    config(['app.frontend_url' => 'https://app.timatic.test/']);
    $this->loginUser(permissions: ['budgets.update', 'budgets.read']);
    $budget = BudgetVersion::factory()->create()->budget;

    $response = $this->postJson("budgets/{$budget->id}/share-link")
        ->assertCreated()
        ->assertJsonPath('data.type', 'budget-share-links')
        ->assertJsonPath('data.id', (string) $budget->id);

    $token = $response->json('data.attributes.token');
    expect($token)->toHaveLength(48)
        ->and($response->json('data.attributes.url'))->toBe("https://app.timatic.test/shared-budgets/{$token}")
        ->and($budget->refresh()->share_token_hash)->toBe(hash('sha256', $token));
    $this->getJson("budgets/{$budget->id}")->assertJsonPath('data.attributes.isShared', true);
});

it('invalidates the previous token when a new share link is created', function () {
    $this->loginUser(permissions: ['budgets.update']);
    $budget = BudgetVersion::factory()->create()->budget;
    $oldToken = $this->postJson("budgets/{$budget->id}/share-link")->json('data.attributes.token');

    $newToken = $this->postJson("budgets/{$budget->id}/share-link")->json('data.attributes.token');

    expect($newToken)->not->toBe($oldToken)
        ->and($budget->refresh()->share_token_hash)->toBe(hash('sha256', $newToken));
});

it('revokes a share link', function () {
    $this->loginUser(permissions: ['budgets.update']);
    $budget = BudgetVersion::factory()->create()->budget;
    $this->postJson("budgets/{$budget->id}/share-link");

    $this->deleteJson("budgets/{$budget->id}/share-link")->assertNoContent();

    expect($budget->refresh()->share_token_hash)->toBeNull();
    $this->loginUser(permissions: ['budgets.read']);
    $this->getJson("budgets/{$budget->id}")->assertJsonPath('data.attributes.isShared', false);
});

it('does not let a user who cannot update the budget create a share link', function () {
    $this->loginUser(permissions: ['budgets.read']);
    $budget = BudgetVersion::factory()->create()->budget;

    $this->postJson("budgets/{$budget->id}/share-link")->assertForbidden();

    expect($budget->refresh()->share_token_hash)->toBeNull();
});

it('does not share a budget that is hidden from the customer', function () {
    $this->loginUser(permissions: ['budgets.update']);
    $budget = BudgetVersion::factory()->create()->budget;
    $budget->forceFill(['show_to_customer' => false])->save();

    $this->postJson("budgets/{$budget->id}/share-link")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Dit budget is verborgen voor de klant en kan daarom niet gedeeld worden.');

    expect($budget->refresh()->share_token_hash)->toBeNull();
});

it('does not let a user who cannot update the budget revoke its share link', function () {
    $this->loginUser(permissions: ['budgets.read']);
    $budget = BudgetVersion::factory()->create()->budget;
    $budget->forceFill(['share_token_hash' => hash('sha256', 'secret-token')])->save();

    $this->deleteJson("budgets/{$budget->id}/share-link")->assertForbidden();

    expect($budget->refresh()->share_token_hash)->toBe(hash('sha256', 'secret-token'));
});
