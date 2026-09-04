<?php

use App\Integrations\TicketService;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GoogleCalendar\Jobs\SyncUserCalendarJob;
use Timatic\GoogleCalendar\OAuthService;
use Timatic\GoogleCalendar\Requests\ListEventsRequest;
use Timatic\GoogleCalendar\ServiceProvider;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('creates an event with the google event id as external_id', function () {
    $user = User::factory()->create([
        'oauth_access_token' => 'test-access-token',
        'oauth_refresh_token' => 'test-refresh-token',
        'oauth_token_expires_at' => now()->addHour()->timestamp,
    ]);

    MockClient::global([
        ListEventsRequest::class => MockResponse::make([
            'items' => [
                [
                    'id' => 'google-event-1',
                    'status' => 'confirmed',
                    'summary' => 'Standup',
                    'start' => ['dateTime' => now()->subMinutes(5)->toRfc3339String()],
                    'end' => ['dateTime' => now()->toRfc3339String()],
                ],
            ],
        ]),
    ]);

    new SyncUserCalendarJob($user)->handle(app(OAuthService::class), app(TicketService::class));

    $event = Event::sole();

    expect($event->source_id)->toBe(ServiceProvider::SOURCE_ID)
        ->and($event->external_id)->toBe('google-event-1');
});

it('does not create a duplicate event when an overlapping sync fetches the same google event again', function () {
    $user = User::factory()->create([
        'oauth_access_token' => 'test-access-token',
        'oauth_refresh_token' => 'test-refresh-token',
        'oauth_token_expires_at' => now()->addHour()->timestamp,
    ]);

    MockClient::global([
        ListEventsRequest::class => MockResponse::make([
            'items' => [
                [
                    'id' => 'google-event-1',
                    'status' => 'confirmed',
                    'summary' => 'Standup',
                    'start' => ['dateTime' => now()->subMinutes(5)->toRfc3339String()],
                    'end' => ['dateTime' => now()->toRfc3339String()],
                ],
            ],
        ]),
    ]);

    new SyncUserCalendarJob($user)->handle(app(OAuthService::class), app(TicketService::class));
    new SyncUserCalendarJob($user)->handle(app(OAuthService::class), app(TicketService::class));

    expect(Event::count())->toBe(1);
});
