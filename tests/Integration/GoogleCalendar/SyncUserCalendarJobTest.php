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

it('stores the recurring event id as external_id so all instances share their sequence', function () {
    $user = User::factory()->create([
        'oauth_access_token' => 'test-access-token',
        'oauth_refresh_token' => 'test-refresh-token',
        'oauth_token_expires_at' => now()->addHour()->timestamp,
    ]);

    MockClient::global([
        ListEventsRequest::class => MockResponse::make([
            'items' => [
                [
                    'id' => 'weekly-sync_20261015T090000Z',
                    'recurringEventId' => 'weekly-sync',
                    'status' => 'confirmed',
                    'summary' => 'Weekly sync',
                    'start' => ['dateTime' => now()->subMinutes(5)->toRfc3339String()],
                    'end' => ['dateTime' => now()->toRfc3339String()],
                ],
            ],
        ]),
    ]);

    new SyncUserCalendarJob($user)->handle(app(OAuthService::class), app(TicketService::class));

    expect(Event::sole()->external_id)->toBe('weekly-sync');
});

it('creates a separate event for each instance of a recurring event', function () {
    $user = User::factory()->create([
        'oauth_access_token' => 'test-access-token',
        'oauth_refresh_token' => 'test-refresh-token',
        'oauth_token_expires_at' => now()->addHour()->timestamp,
    ]);

    MockClient::global([
        ListEventsRequest::class => MockResponse::make([
            'items' => [
                [
                    'id' => 'check-in_1',
                    'recurringEventId' => 'check-in',
                    'status' => 'confirmed',
                    'summary' => 'Check-in',
                    'start' => ['dateTime' => now()->subMinutes(40)->toRfc3339String()],
                    'end' => ['dateTime' => now()->subMinutes(30)->toRfc3339String()],
                ],
                [
                    'id' => 'check-in_2',
                    'recurringEventId' => 'check-in',
                    'status' => 'confirmed',
                    'summary' => 'Check-in',
                    'start' => ['dateTime' => now()->subMinutes(10)->toRfc3339String()],
                    'end' => ['dateTime' => now()->toRfc3339String()],
                ],
            ],
        ]),
    ]);

    new SyncUserCalendarJob($user)->handle(app(OAuthService::class), app(TicketService::class));
    new SyncUserCalendarJob($user)->handle(app(OAuthService::class), app(TicketService::class));

    expect(Event::query()->where('external_id', 'check-in')->count())->toBe(2);
});

it('creates an event for every attendee of the same calendar event', function () {
    $users = User::factory()->count(2)->create([
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

    foreach ($users as $user) {
        new SyncUserCalendarJob($user)->handle(app(OAuthService::class), app(TicketService::class));
    }

    expect(Event::query()->pluck('user_id')->sort()->values()->all())
        ->toEqual($users->pluck('id')->sort()->values()->all());
});

it('does not create an event for a calendar event marked as private', function () {
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
                    'summary' => 'Tandarts',
                    'visibility' => 'private',
                    'start' => ['dateTime' => now()->subMinutes(5)->toRfc3339String()],
                    'end' => ['dateTime' => now()->toRfc3339String()],
                ],
            ],
        ]),
    ]);

    new SyncUserCalendarJob($user)->handle(app(OAuthService::class), app(TicketService::class));

    expect(Event::count())->toBe(0);
});

it('does not create an event for a calendar event marked as confidential', function () {
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
                    'summary' => 'Beoordelingsgesprek',
                    'visibility' => 'confidential',
                    'start' => ['dateTime' => now()->subMinutes(5)->toRfc3339String()],
                    'end' => ['dateTime' => now()->toRfc3339String()],
                ],
            ],
        ]),
    ]);

    new SyncUserCalendarJob($user)->handle(app(OAuthService::class), app(TicketService::class));

    expect(Event::count())->toBe(0);
});

it('does not store the description of a calendar event', function () {
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
                    'description' => 'Bespreken: salaris van Jan en het conflict met de buren',
                    'start' => ['dateTime' => now()->subMinutes(5)->toRfc3339String()],
                    'end' => ['dateTime' => now()->toRfc3339String()],
                ],
            ],
        ]),
    ]);

    new SyncUserCalendarJob($user)->handle(app(OAuthService::class), app(TicketService::class));

    expect(Event::sole()->description)->toBeNull();
});
