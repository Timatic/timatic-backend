<?php

use App\Events\SocialiteRedirecting;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

beforeEach(function () {
    config([
        'auth.socialite_driver' => 'google',
        'services.google.client_id' => 'a-client-id',
    ]);
});

it('dispatches the SocialiteRedirecting event', function () {
    Event::fake([SocialiteRedirecting::class]);

    $mock = Mockery::mock(GoogleProvider::class);
    $mock->shouldReceive('scopes')->andReturnSelf();
    $mock->shouldReceive('with')->andReturnSelf();
    $mock->shouldReceive('redirect->getTargetUrl')->andReturn('https://accounts.google.com/oauth2/auth');

    Socialite::shouldReceive('driver')->andReturn($mock);

    $this->get(route('auth.redirect'));

    Event::assertDispatched(SocialiteRedirecting::class);
});

it('passes scopes added by listeners to the socialite driver', function () {
    Event::listen(SocialiteRedirecting::class, function (SocialiteRedirecting $event): void {
        $event->addScopes('https://www.googleapis.com/auth/calendar.readonly');
    });

    $capturedScopes = [];

    $mock = Mockery::mock(GoogleProvider::class);
    $mock->shouldReceive('scopes')
        ->once()
        ->withArgs(function (array $scopes) use (&$capturedScopes): bool {
            $capturedScopes = $scopes;

            return true;
        })
        ->andReturnSelf();
    $mock->shouldReceive('with')->andReturnSelf();
    $mock->shouldReceive('redirect->getTargetUrl')->andReturn('https://accounts.google.com/oauth2/auth');

    Socialite::shouldReceive('driver')->andReturn($mock);

    $this->get(route('auth.redirect'));

    expect($capturedScopes)->toContain('https://www.googleapis.com/auth/calendar.readonly');
});

it('does not call scopes when no listener adds any', function () {
    Event::fake([SocialiteRedirecting::class]);

    $mock = Mockery::mock(GoogleProvider::class);
    $mock->shouldReceive('scopes')->never();
    $mock->shouldReceive('with')->andReturnSelf();
    $mock->shouldReceive('redirect->getTargetUrl')->andReturn('https://accounts.google.com/oauth2/auth');

    Socialite::shouldReceive('driver')->andReturn($mock);

    $this->get(route('auth.redirect'));
});

it('refuses to start a login flow for a provider that has no credentials', function () {
    config([
        'auth.socialite_driver' => 'google',
        'services.google.client_id' => null,
    ]);

    $this->get(route('auth.redirect'))->assertStatus(503);
});

it('makes a user who signed out pass the identity provider again', function (string $driver, string $prompt) {
    config([
        'auth.socialite_driver' => $driver,
        "services.{$driver}.client_id" => 'a-client-id',
        'api_clients.clients.web.redirect_uris' => ['https://app.timatic.test/auth/callback'],
    ]);

    $capturedParameters = [];

    $mock = Mockery::mock(GoogleProvider::class);
    $mock->shouldReceive('scopes')->andReturnSelf();
    $mock->shouldReceive('with')
        ->once()
        ->withArgs(function (array $parameters) use (&$capturedParameters): bool {
            $capturedParameters = $parameters;

            return true;
        })
        ->andReturnSelf();
    $mock->shouldReceive('redirect->getTargetUrl')->andReturn('https://provider.test/authorize');

    Socialite::shouldReceive('driver')->andReturn($mock);

    $authorizeResponse = $this->get(route('oauth.authorize.show', [
        'client_id' => 'web',
        'redirect_uri' => 'https://app.timatic.test/auth/callback',
        'state' => 'state-123',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('a', 64), true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
        'reauthenticate' => 1,
    ]));

    $this->get((string) $authorizeResponse->headers->get('Location'));

    expect($capturedParameters)->toHaveKey('prompt', $prompt);
})->with([
    ['google', 'login'],
    ['auth0', 'select_account'],
]);
