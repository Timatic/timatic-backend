<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GoogleCalendar\Models\GoogleCalendarConnection;
use Timatic\GoogleCalendar\OAuthService;
use Timatic\GoogleCalendar\Requests\ExchangeCodeRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('asks google for calendar access on its own rather than at login', function () {
    config([
        'google_calendar.client_id' => 'a-client-id',
        'google_calendar.redirect' => 'https://api.timatic.test/google-calendar/callback',
    ]);

    $user = User::factory()->create();

    $url = URL::temporarySignedRoute('google_calendar.connect', now()->addMinutes(5), ['user' => $user->id]);

    $location = (string) $this->get($url)->headers->get('Location');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
        ->and($query['scope'])->toBe('https://www.googleapis.com/auth/calendar.readonly')
        ->and($query['access_type'])->toBe('offline')
        ->and($query['redirect_uri'])->toBe('https://api.timatic.test/google-calendar/callback')
        ->and(Cache::get(OAuthService::STATE_CACHE_PREFIX.$query['state']))->toBe($user->id);
});

it('refuses a connect url without a valid signature', function () {
    $user = User::factory()->create();

    $this->get(route('google_calendar.connect', ['user' => $user->id]))->assertForbidden();
});

it('stores the calendar tokens the user granted', function () {
    config(['app.frontend_url' => 'https://app.timatic.test']);

    $user = User::factory()->create();

    Cache::put(OAuthService::STATE_CACHE_PREFIX.'a-nonce', $user->id);

    MockClient::global([
        ExchangeCodeRequest::class => MockResponse::make([
            'access_token' => 'an-access-token',
            'refresh_token' => 'a-refresh-token',
            'expires_in' => 3600,
        ]),
    ]);

    $this->get(route('google_calendar.callback', ['code' => 'a-code', 'state' => 'a-nonce']))
        ->assertRedirect('https://app.timatic.test/');

    $connection = GoogleCalendarConnection::sole();

    expect($connection->user_id)->toBe($user->id)
        ->and($connection->refresh_token)->toBe('a-refresh-token')
        ->and($connection->access_token)->toBe('an-access-token');
});

it('leaves the calendar unconnected when the user declines at google', function () {
    config(['app.frontend_url' => 'https://app.timatic.test']);

    $this->get(route('google_calendar.callback', ['error' => 'access_denied']))
        ->assertRedirect('https://app.timatic.test/');

    expect(GoogleCalendarConnection::count())->toBe(0);
});
