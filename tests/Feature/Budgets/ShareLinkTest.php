<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

    Auth::forgetGuards();
    $this->getJson("budgets/{$budget->id}", ['X-Share-Token' => $oldToken])->assertUnauthorized();
    $this->getJson("budgets/{$budget->id}", ['X-Share-Token' => $newToken])->assertSuccessful();
});

it('revokes a share link', function () {
    $this->loginUser(permissions: ['budgets.update']);
    $budget = BudgetVersion::factory()->create()->budget;
    $token = $this->postJson("budgets/{$budget->id}/share-link")->json('data.attributes.token');

    $this->deleteJson("budgets/{$budget->id}/share-link")->assertNoContent();

    expect($budget->refresh()->share_token_hash)->toBeNull();
    $this->loginUser(permissions: ['budgets.read']);
    $this->getJson("budgets/{$budget->id}")->assertJsonPath('data.attributes.isShared', false);
    Auth::forgetGuards();
    $this->getJson("budgets/{$budget->id}", ['X-Share-Token' => $token])->assertUnauthorized();
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

it('revokes the share link when the budget is hidden from the customer', function () {
    $this->loginUser(permissions: ['budgets.update']);
    $budget = BudgetVersion::factory()->create()->budget;
    $budget->forceFill(['share_token_hash' => hash('sha256', 'secret-token')])->save();

    $this->patchJson("budgets/{$budget->id}", ['type' => 'budgets', 'attributes' => ['showToCustomer' => false]])->assertSuccessful();
    $this->patchJson("budgets/{$budget->id}", ['type' => 'budgets', 'attributes' => ['showToCustomer' => true]])->assertSuccessful();

    expect($budget->refresh()->share_token_hash)->toBeNull();
    Auth::forgetGuards();
    $this->getJson("budgets/{$budget->id}", ['X-Share-Token' => 'secret-token'])->assertUnauthorized();
});

it('does not let a user who cannot update the budget revoke its share link', function () {
    $this->loginUser(permissions: ['budgets.read']);
    $budget = BudgetVersion::factory()->create()->budget;
    $budget->forceFill(['share_token_hash' => hash('sha256', 'secret-token')])->save();

    $this->deleteJson("budgets/{$budget->id}/share-link")->assertForbidden();

    expect($budget->refresh()->share_token_hash)->toBe(hash('sha256', 'secret-token'));
});

it('reads its budget with a share token, without prices', function () {
    $customer = Customer::factory()->create(['name' => 'Acme', 'hourly_rate' => 95]);
    $budget = Budget::factory()->create([
        'customer_id' => $customer->id,
        'budget_type_id' => 'project',
        'started_at' => '2026-01-10 00:00:00',
        'ended_at' => '2026-06-30 00:00:00',
        'renewal_frequency' => null,
        'share_token_hash' => hash('sha256', 'secret-token'),
    ]);
    BudgetVersion::factory()->for($budget)->create([
        'title' => 'Website',
        'description' => 'Maintenance',
        'initial_minutes' => 600,
        'total_price' => 1000,
        'contract_id' => 'contract-1',
        'change_id' => 'change-1',
        'effective_from' => '2026-01-10 00:00:00',
    ]);

    $response = $this->getJson("budgets/{$budget->id}?include=customer,budgetType", ['X-Share-Token' => 'secret-token']);

    $response->assertSuccessful()
        ->assertJsonPath('data.attributes', [
            'budgetTypeId' => 'project',
            'customerId' => (string) $customer->id,
            'showToCustomer' => true,
            'title' => 'Website',
            'description' => 'Maintenance',
            'startedAt' => '2026-01-10T00:00:00.000000Z',
            'endedAt' => '2026-06-30T00:00:00.000000Z',
            'initialMinutes' => 600,
            'isArchived' => false,
            'isShared' => true,
            'renewalFrequency' => null,
            'createdAt' => $budget->created_at->toJSON(),
            'updatedAt' => $budget->activeVersion()->updated_at->toJSON(),
        ]);
    $included = collect($response->json('included'));
    expect($included->firstWhere('type', 'customers')['attributes'])->toBe([
        'name' => 'Acme',
        'isOwnOrganization' => false,
        'createdAt' => $customer->created_at->toJSON(),
        'updatedAt' => $customer->updated_at->toJSON(),
    ])
        ->and($included->firstWhere('type', 'budgetTypes')['attributes']['title'])->toBe('Project');
});

it('hides period prices from a share token while users keep seeing them', function () {
    $budget = Budget::factory()->create([
        'renewal_frequency' => null,
        'share_token_hash' => hash('sha256', 'secret-token'),
    ]);
    BudgetVersion::factory()->for($budget)->create(['total_price' => 1000, 'effective_from' => $budget->started_at]);
    $this->loginUser(permissions: ['budgets.read']);
    $this->getJson("budgets/{$budget->id}/periods")->assertJsonPath('data.0.attributes.totalPrice', '1000.00');
    Auth::forgetGuards();

    $response = $this->getJson("budgets/{$budget->id}/periods", ['X-Share-Token' => 'secret-token']);

    $response->assertSuccessful();
    expect($response->json('data.0.attributes'))->not->toHaveKey('totalPrice')
        ->and($response->json('data.0.attributes.initialMinutes'))->toBe($budget->activeVersion()->initial_minutes);
});

it('lets a bearer token win over a share token sent along', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('budgets.read');
    ApiToken::query()->create(['user_id' => $user->id, 'title' => 'Chrome op Mac', 'key' => hash('sha512', 'plain-text-token')]);
    $budget = Budget::factory()->create(['share_token_hash' => hash('sha256', 'secret-token')]);
    BudgetVersion::factory()->for($budget)->create(['total_price' => 1000, 'effective_from' => $budget->started_at]);

    $this->getJson("budgets/{$budget->id}", ['Authorization' => 'Bearer plain-text-token', 'X-Share-Token' => 'secret-token'])
        ->assertSuccessful()
        ->assertJsonPath('data.attributes.totalPrice', 1000);
});

it('reads the time spent totals of its budget with a share token', function () {
    $budget = Budget::factory()->create([
        'renewal_frequency' => null,
        'share_token_hash' => hash('sha256', 'secret-token'),
    ]);
    BudgetVersion::factory()->for($budget)->create(['effective_from' => $budget->started_at]);

    $this->getJson("budget-time-spent-totals?filter[budgetId]={$budget->id}&periodUnit=month", ['X-Share-Token' => 'secret-token'])
        ->assertSuccessful()
        ->assertJsonPath('data.0.type', 'budget-time-spent-totals');
});

it('denies a share token everything but reading its own budget', function (string $method, string $uri) {
    $budget = BudgetVersion::factory()->create()->budget;
    $budget->forceFill(['share_token_hash' => hash('sha256', 'secret-token')])->save();
    $otherBudget = BudgetVersion::factory()->create()->budget;
    $uri = str_replace(['{budget}', '{otherBudget}'], [$budget->id, $otherBudget->id], $uri);

    $this->json($method, $uri, [], ['X-Share-Token' => 'secret-token'])->assertForbidden();
})->with([
    'another budget' => ['GET', 'budgets/{otherBudget}'],
    'a budget that does not exist' => ['GET', 'budgets/999999'],
    'the periods of another budget' => ['GET', 'budgets/{otherBudget}/periods'],
    'the totals of another budget' => ['GET', 'budget-time-spent-totals?filter[budgetId]={otherBudget}&periodUnit=month'],
    'all budgets' => ['GET', 'budgets'],
    'entries' => ['GET', 'entries?filter[budgetId]={budget}'],
    'included entries' => ['GET', 'budgets/{budget}?include=entries'],
    'included users' => ['GET', 'budgets/{budget}?include=allowedUsers'],
    'includes as an array' => ['GET', 'budgets/{budget}?include[]=customer'],
    'a budget filter as an array' => ['GET', 'budget-time-spent-totals?filter[budgetId][]={budget}&periodUnit=month'],
    'updating the budget' => ['PATCH', 'budgets/{budget}'],
    'a new share link' => ['POST', 'budgets/{budget}/share-link'],
]);

it('does not accept the share token of a budget hidden from the customer', function () {
    $budget = BudgetVersion::factory()->create()->budget;
    $budget->forceFill(['share_token_hash' => hash('sha256', 'secret-token'), 'show_to_customer' => false])->save();

    $this->getJson("budgets/{$budget->id}", ['X-Share-Token' => 'secret-token'])->assertUnauthorized();
});

it('throttles a share token to 60 requests a minute', function () {
    $budget = BudgetVersion::factory()->create()->budget;
    $budget->forceFill(['share_token_hash' => hash('sha256', 'secret-token')])->save();
    foreach (range(1, 60) as $request) {
        $this->getJson("budgets/{$budget->id}", ['X-Share-Token' => 'secret-token'])->assertSuccessful();
    }

    $this->getJson("budgets/{$budget->id}", ['X-Share-Token' => 'secret-token'])->assertTooManyRequests();
});
