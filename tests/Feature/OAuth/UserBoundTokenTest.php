<?php

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('authenticates as the user behind a user bound token', function () {
    $user = User::factory()->create();

    ApiToken::query()->create([
        'user_id' => $user->id,
        'title' => 'Chrome op Mac',
        'key' => hash('sha512', 'plain-text-token'),
        'expires_at' => now()->addDays(90),
    ]);

    $this->getJson(route('me'), ['Authorization' => 'Bearer plain-text-token'])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $user->id);
});

it('keeps authenticating machine tokens as the token itself', function () {
    /** @var ApiToken $apiToken */
    $apiToken = ApiToken::query()->create([
        'external_id' => 'machine',
        'title' => 'Machine',
        'key' => hash('sha512', 'machine-token'),
    ]);

    $apiToken->givePermissionTo('budget-types.read');

    $this->getJson(route('budget-types.index'), ['Authorization' => 'Bearer machine-token'])->assertOk();

    expect(User::query()->count())->toEqual(0);
});

it('records when a token was last used', function () {
    $user = User::factory()->create();

    /** @var ApiToken $apiToken */
    $apiToken = ApiToken::query()->create([
        'user_id' => $user->id,
        'title' => 'Chrome op Mac',
        'key' => hash('sha512', 'plain-text-token'),
    ]);

    $this->getJson(route('me'), ['Authorization' => 'Bearer plain-text-token'])->assertOk();

    expect($apiToken->refresh()->last_used_at)->not->toBeNull();
});

it('refuses an expired token', function () {
    $user = User::factory()->create();

    ApiToken::query()->create([
        'user_id' => $user->id,
        'title' => 'Chrome op Mac',
        'key' => hash('sha512', 'plain-text-token'),
        'expires_at' => now()->subDay(),
    ]);

    $this->getJson(route('me'), ['Authorization' => 'Bearer plain-text-token'])->assertUnauthorized();
});

it('revokes the token it is called with', function () {
    $user = User::factory()->create();

    ApiToken::query()->create([
        'user_id' => $user->id,
        'title' => 'Chrome op Mac',
        'key' => hash('sha512', 'plain-text-token'),
    ]);

    $this->deleteJson(route('oauth.token.destroy'), [], ['Authorization' => 'Bearer plain-text-token'])
        ->assertNoContent();

    expect(ApiToken::query()->count())->toEqual(0);

    Auth::forgetGuards();

    $this->getJson(route('me'), ['Authorization' => 'Bearer plain-text-token'])->assertUnauthorized();
});
