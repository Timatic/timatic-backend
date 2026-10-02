<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GoogleCalendar\Models\GoogleCalendarConnection;
use Timatic\GoogleCalendar\OAuthService;
use Timatic\GoogleCalendar\Requests\RevokeTokenRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('revokes the grant at google and forgets the tokens', function () {
    $connection = GoogleCalendarConnection::factory()->create(['refresh_token' => 'test-refresh-token']);

    $mock = MockClient::global([
        RevokeTokenRequest::class => MockResponse::make([], 200),
    ]);

    app(OAuthService::class)->disconnect($connection);

    $mock->assertSent(function (RevokeTokenRequest $request): bool {
        return $request->body()->all() === ['token' => 'test-refresh-token'];
    });

    expect(GoogleCalendarConnection::count())->toBe(0);
});

it('forgets the tokens even when google refuses the revocation', function () {
    $connection = GoogleCalendarConnection::factory()->create(['refresh_token' => 'test-refresh-token']);

    MockClient::global([
        RevokeTokenRequest::class => MockResponse::make(['error' => 'invalid_token'], 400),
    ]);

    app(OAuthService::class)->disconnect($connection);

    expect(GoogleCalendarConnection::count())->toBe(0);
});
