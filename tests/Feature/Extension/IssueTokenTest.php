<?php

use App\Models\ApiToken;
use App\Models\User;
use App\Services\ExtensionAuthorizationService;

beforeEach(function () {
    config(['extension.ids' => ['abcdefghijklmnopabcdefghijklmnop']]);

    $this->redirectUri = 'https://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org/';
    $this->codeVerifier = str_repeat('a', 64);
    $this->codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $this->codeVerifier, true)), '+/', '-_'), '=');
});

it('exchanges a code for a user bound token', function () {
    $user = User::factory()->create();

    $code = app(ExtensionAuthorizationService::class)->issueCode($user, $this->codeChallenge, $this->redirectUri);

    $response = $this->postJson(route('extension.token.store'), [
        'code' => $code,
        'code_verifier' => $this->codeVerifier,
        'redirect_uri' => $this->redirectUri,
        'device_name' => 'Chrome op Mac',
    ])->assertCreated();

    /** @var ApiToken $apiToken */
    $apiToken = ApiToken::query()->sole();

    expect($apiToken->user_id)->toEqual($user->id);
    expect($apiToken->title)->toEqual('Chrome op Mac');
    expect($apiToken->key)->toEqual(hash('sha512', $response->json('token')));
    expect($apiToken->expires_at?->toDateString())->toEqual(now()->addDays(90)->toDateString());
    expect($response->json('user.id'))->toEqual($user->id);
});

it('refuses a code verifier that does not match the challenge', function () {
    $user = User::factory()->create();

    $code = app(ExtensionAuthorizationService::class)->issueCode($user, $this->codeChallenge, $this->redirectUri);

    $this->postJson(route('extension.token.store'), [
        'code' => $code,
        'code_verifier' => str_repeat('b', 64),
        'redirect_uri' => $this->redirectUri,
        'device_name' => 'Chrome op Mac',
    ])->assertStatus(400);

    expect(ApiToken::query()->count())->toEqual(0);
});

it('refuses to use the same code twice', function () {
    $user = User::factory()->create();

    $code = app(ExtensionAuthorizationService::class)->issueCode($user, $this->codeChallenge, $this->redirectUri);

    $body = [
        'code' => $code,
        'code_verifier' => $this->codeVerifier,
        'redirect_uri' => $this->redirectUri,
        'device_name' => 'Chrome op Mac',
    ];

    $this->postJson(route('extension.token.store'), $body)->assertCreated();
    $this->postJson(route('extension.token.store'), $body)->assertStatus(410);

    expect(ApiToken::query()->count())->toEqual(1);
});

it('refuses an expired code', function () {
    $user = User::factory()->create();

    $code = app(ExtensionAuthorizationService::class)->issueCode($user, $this->codeChallenge, $this->redirectUri);

    $this->travel(2)->minutes();

    $this->postJson(route('extension.token.store'), [
        'code' => $code,
        'code_verifier' => $this->codeVerifier,
        'redirect_uri' => $this->redirectUri,
        'device_name' => 'Chrome op Mac',
    ])->assertStatus(410);
});
