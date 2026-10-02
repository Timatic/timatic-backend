<?php

use App\Models\ApiToken;
use App\Models\User;
use App\Services\ApiClientRegistry;
use App\Services\AuthorizationCodeService;

beforeEach(function () {
    config([
        'api_clients.clients.extension.redirect_uris' => ['https://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org/'],
        'api_clients.clients.web.redirect_uris' => ['https://app.timatic.test/auth/callback'],
    ]);

    $this->redirectUri = 'https://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org/';
    $this->codeVerifier = str_repeat('a', 64);
    $this->codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $this->codeVerifier, true)), '+/', '-_'), '=');
    $this->extension = app(ApiClientRegistry::class)->findOrFail('extension');
});

it('exchanges a code for a user bound token', function () {
    $user = User::factory()->create();

    $code = app(AuthorizationCodeService::class)
        ->issueCode($this->extension, $user, $this->codeChallenge, $this->redirectUri);

    $response = $this->postJson(route('oauth.token.store'), [
        'client_id' => 'extension',
        'code' => $code,
        'code_verifier' => $this->codeVerifier,
        'redirect_uri' => $this->redirectUri,
        'device_name' => 'Chrome op Mac',
    ])->assertCreated();

    /** @var ApiToken $apiToken */
    $apiToken = ApiToken::query()->sole();

    expect($apiToken->user_id)->toEqual($user->id);
    expect($apiToken->title)->toEqual('Chrome op Mac');
    expect($apiToken->description)->toEqual('Browser extension');
    expect($apiToken->key)->toEqual(hash('sha512', $response->json('token')));
    expect($apiToken->expires_at?->toDateString())->toEqual(now()->addDays(90)->toDateString());
    expect($response->json('user.id'))->toEqual($user->id);
});

it('issues a token for the web client with its own lifetime', function () {
    $user = User::factory()->create();
    $redirectUri = 'https://app.timatic.test/auth/callback';

    $code = app(AuthorizationCodeService::class)->issueCode(
        app(ApiClientRegistry::class)->findOrFail('web'),
        $user,
        $this->codeChallenge,
        $redirectUri,
    );

    $this->postJson(route('oauth.token.store'), [
        'client_id' => 'web',
        'code' => $code,
        'code_verifier' => $this->codeVerifier,
        'redirect_uri' => $redirectUri,
        'device_name' => 'Chrome op Mac',
    ])->assertCreated();

    /** @var ApiToken $apiToken */
    $apiToken = ApiToken::query()->sole();

    expect($apiToken->description)->toEqual('Timatic web app');
    expect($apiToken->expires_at?->toDateString())->toEqual(now()->addDays(30)->toDateString());
});

it('refuses to claim a code with another client', function () {
    $user = User::factory()->create();

    $code = app(AuthorizationCodeService::class)
        ->issueCode($this->extension, $user, $this->codeChallenge, $this->redirectUri);

    $this->postJson(route('oauth.token.store'), [
        'client_id' => 'web',
        'code' => $code,
        'code_verifier' => $this->codeVerifier,
        'redirect_uri' => 'https://app.timatic.test/auth/callback',
        'device_name' => 'Chrome op Mac',
    ])->assertStatus(400);

    expect(ApiToken::query()->count())->toEqual(0);
});

it('refuses a redirect uri that belongs to another client', function () {
    $this->postJson(route('oauth.token.store'), [
        'client_id' => 'web',
        'code' => 'a-code',
        'code_verifier' => $this->codeVerifier,
        'redirect_uri' => $this->redirectUri,
        'device_name' => 'Chrome op Mac',
    ])->assertStatus(422);
});

it('refuses a code verifier that does not match the challenge', function () {
    $user = User::factory()->create();

    $code = app(AuthorizationCodeService::class)
        ->issueCode($this->extension, $user, $this->codeChallenge, $this->redirectUri);

    $this->postJson(route('oauth.token.store'), [
        'client_id' => 'extension',
        'code' => $code,
        'code_verifier' => str_repeat('b', 64),
        'redirect_uri' => $this->redirectUri,
        'device_name' => 'Chrome op Mac',
    ])->assertStatus(400);

    expect(ApiToken::query()->count())->toEqual(0);
});

it('refuses to use the same code twice', function () {
    $user = User::factory()->create();

    $code = app(AuthorizationCodeService::class)
        ->issueCode($this->extension, $user, $this->codeChallenge, $this->redirectUri);

    $body = [
        'client_id' => 'extension',
        'code' => $code,
        'code_verifier' => $this->codeVerifier,
        'redirect_uri' => $this->redirectUri,
        'device_name' => 'Chrome op Mac',
    ];

    $this->postJson(route('oauth.token.store'), $body)->assertCreated();
    $this->postJson(route('oauth.token.store'), $body)->assertStatus(410);

    expect(ApiToken::query()->count())->toEqual(1);
});

it('refuses an expired code', function () {
    $user = User::factory()->create();

    $code = app(AuthorizationCodeService::class)
        ->issueCode($this->extension, $user, $this->codeChallenge, $this->redirectUri);

    $this->travel(2)->minutes();

    $this->postJson(route('oauth.token.store'), [
        'client_id' => 'extension',
        'code' => $code,
        'code_verifier' => $this->codeVerifier,
        'redirect_uri' => $this->redirectUri,
        'device_name' => 'Chrome op Mac',
    ])->assertStatus(410);
});
