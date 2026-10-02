<?php

use App\Integrations\TicketService;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GoogleCalendar\Jobs\SyncUserCalendarJob;
use Timatic\GoogleCalendar\Models\GoogleCalendarConnection;
use Timatic\GoogleCalendar\OAuthService;
use Timatic\GoogleCalendar\Requests\ListEventsRequest;
use Timatic\GoogleCalendar\ServiceProvider;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('creates an event with the google event id as external_id', function () {
    $connection = GoogleCalendarConnection::factory()->create();

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

    new SyncUserCalendarJob($connection)->handle(app(OAuthService::class), app(TicketService::class));

    $event = Event::sole();

    expect($event->source_id)->toBe(ServiceProvider::SOURCE_ID)
        ->and($event->external_id)->toBe('google-event-1');
});

it('does not create a duplicate event when an overlapping sync fetches the same google event again', function () {
    $connection = GoogleCalendarConnection::factory()->create();

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

    new SyncUserCalendarJob($connection)->handle(app(OAuthService::class), app(TicketService::class));
    new SyncUserCalendarJob($connection)->handle(app(OAuthService::class), app(TicketService::class));

    expect(Event::count())->toBe(1);
});

it('does not create an event for a calendar event marked as private', function () {
    $connection = GoogleCalendarConnection::factory()->create();

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

    new SyncUserCalendarJob($connection)->handle(app(OAuthService::class), app(TicketService::class));

    expect(Event::count())->toBe(0);
});

it('does not create an event for a calendar event marked as confidential', function () {
    $connection = GoogleCalendarConnection::factory()->create();

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

    new SyncUserCalendarJob($connection)->handle(app(OAuthService::class), app(TicketService::class));

    expect(Event::count())->toBe(0);
});

it('does not store the description of a calendar event', function () {
    $connection = GoogleCalendarConnection::factory()->create();

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

    new SyncUserCalendarJob($connection)->handle(app(OAuthService::class), app(TicketService::class));

    expect(Event::sole()->description)->toBeNull();
});
