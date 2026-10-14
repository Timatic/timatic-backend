<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GoogleCalendar\OAuthService;
use Timatic\GoogleCalendar\Requests\RevokeTokenRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('revokes the grant at google and forgets the tokens', function () {
    $user = User::factory()->create([
        'oauth_access_token' => 'test-access-token',
        'oauth_refresh_token' => 'test-refresh-token',
        'oauth_token_expires_at' => now()->addHour()->timestamp,
    ]);

    $mock = MockClient::global([
        RevokeTokenRequest::class => MockResponse::make([], 200),
    ]);

    app(OAuthService::class)->disconnect($user);

    $mock->assertSent(function (RevokeTokenRequest $request): bool {
        return $request->body()->all() === ['token' => 'test-refresh-token'];
    });

    expect($user->refresh()->oauth_refresh_token)->toBeNull()
        ->and($user->oauth_access_token)->toBeNull()
        ->and($user->oauth_token_expires_at)->toBe(0);
});

it('forgets the tokens even when google refuses the revocation', function () {
    $user = User::factory()->create([
        'oauth_access_token' => 'test-access-token',
        'oauth_refresh_token' => 'test-refresh-token',
        'oauth_token_expires_at' => now()->addHour()->timestamp,
    ]);

    MockClient::global([
        RevokeTokenRequest::class => MockResponse::make(['error' => 'invalid_token'], 400),
    ]);

    app(OAuthService::class)->disconnect($user);

    expect($user->refresh()->oauth_refresh_token)->toBeNull();
});
