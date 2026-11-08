<?php

use App\Integrations\Suggestions\CustomerEmailDomainResolver;
use App\Models\Customer;
use App\Models\CustomerEmailDomain;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Gmail\Jobs\SyncUserMailJob;
use Timatic\Gmail\Models\GmailConnection;
use Timatic\Gmail\OAuthService;
use Timatic\Gmail\Requests\GetMessageRequest;
use Timatic\Gmail\Requests\GetProfileRequest;
use Timatic\Gmail\Requests\ListHistoryRequest;
use Timatic\Gmail\ServiceProvider;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('creates an email sent event without duration for the customer among the recipients', function () {
    $acme = CustomerEmailDomain::factory()->create(['domain' => 'acme.com'])->customer;
    $connection = GmailConnection::factory()->create(['history_id' => '1000']);

    MockClient::global([
        ListHistoryRequest::class => MockResponse::make([
            'history' => [
                ['id' => '1001', 'messagesAdded' => [['message' => ['id' => 'message-1', 'threadId' => 'thread-1', 'labelIds' => ['SENT']]]]],
            ],
            'historyId' => '1050',
        ]),
        GetMessageRequest::class => MockResponse::make([
            'id' => 'message-1',
            'threadId' => 'thread-1',
            'labelIds' => ['SENT'],
            'internalDate' => '1793872800123',
            'payload' => ['headers' => [
                ['name' => 'To', 'value' => '"Doe, Jane" <jane@unknown.org>'],
                ['name' => 'Cc', 'value' => 'John <john@acme.com>, hank@globex.com'],
                ['name' => 'Subject', 'value' => 'Quote for the new website'],
            ]],
        ]),
    ]);

    new SyncUserMailJob($connection)->handle(app(OAuthService::class), app(CustomerEmailDomainResolver::class));

    $event = Event::sole();

    expect($event->source_id)->toBe(ServiceProvider::SOURCE_ID)
        ->and($event->event_type_id)->toBe('email_sent')
        ->and($event->external_id)->toBe('thread-1')
        ->and($event->user_id)->toBe($connection->user_id)
        ->and($event->title)->toBe('Quote for the new website')
        ->and($event->customer_id)->toEqual($acme?->id)
        ->and($event->started_at)->toBeNull()
        ->and($event->ended_at->toIso8601String())->toBe('2026-11-05T10:00:00+00:00');
});

it('creates an email draft saved event for a draft', function () {
    $connection = GmailConnection::factory()->create(['history_id' => '1000']);

    MockClient::global([
        ListHistoryRequest::class => MockResponse::make([
            'history' => [
                ['id' => '1001', 'messagesAdded' => [['message' => ['id' => 'draft-message-1', 'threadId' => 'thread-1', 'labelIds' => ['DRAFT']]]]],
            ],
            'historyId' => '1050',
        ]),
        GetMessageRequest::class => MockResponse::make([
            'id' => 'draft-message-1',
            'threadId' => 'thread-1',
            'labelIds' => ['DRAFT'],
            'internalDate' => '1793872800000',
            'payload' => ['headers' => [['name' => 'Subject', 'value' => 'Quote for the new website']]],
        ]),
    ]);

    new SyncUserMailJob($connection)->handle(app(OAuthService::class), app(CustomerEmailDomainResolver::class));

    expect(Event::sole()->event_type_id)->toBe('email_draft_saved');
});

it('creates an email sent event for a draft that was labelled sent', function () {
    $connection = GmailConnection::factory()->create(['history_id' => '1000']);

    MockClient::global([
        ListHistoryRequest::class => MockResponse::make([
            'history' => [
                ['id' => '1001', 'labelsAdded' => [['message' => ['id' => 'message-1', 'threadId' => 'thread-1', 'labelIds' => ['SENT']], 'labelIds' => ['SENT']]]],
            ],
            'historyId' => '1050',
        ]),
        GetMessageRequest::class => MockResponse::make([
            'id' => 'message-1',
            'threadId' => 'thread-1',
            'labelIds' => ['SENT'],
            'internalDate' => '1793872800000',
            'payload' => ['headers' => []],
        ]),
    ]);

    new SyncUserMailJob($connection)->handle(app(OAuthService::class), app(CustomerEmailDomainResolver::class));

    expect(Event::sole()->event_type_id)->toBe('email_sent');
});

it('leaves the customer unknown for mail to the own organization only', function () {
    CustomerEmailDomain::factory()
        ->for(Customer::factory()->state(['is_own_organization' => true]))
        ->create(['domain' => 'pionect.nl']);
    $connection = GmailConnection::factory()->create(['history_id' => '1000']);

    MockClient::global([
        ListHistoryRequest::class => MockResponse::make([
            'history' => [
                ['id' => '1001', 'messagesAdded' => [['message' => ['id' => 'message-1', 'threadId' => 'thread-1', 'labelIds' => ['SENT']]]]],
            ],
            'historyId' => '1050',
        ]),
        GetMessageRequest::class => MockResponse::make([
            'id' => 'message-1',
            'threadId' => 'thread-1',
            'labelIds' => ['SENT'],
            'internalDate' => '1793872800000',
            'payload' => ['headers' => [['name' => 'To', 'value' => 'colleague@pionect.nl']]],
        ]),
    ]);

    new SyncUserMailJob($connection)->handle(app(OAuthService::class), app(CustomerEmailDomainResolver::class));

    expect(Event::sole()->customer_id)->toBeNull();
});

it('reads on from the stored history id and remembers where it stopped', function () {
    $connection = GmailConnection::factory()->create(['history_id' => '1000']);

    $mock = MockClient::global([
        ListHistoryRequest::class => MockResponse::make(['historyId' => '1050']),
    ]);

    new SyncUserMailJob($connection)->handle(app(OAuthService::class), app(CustomerEmailDomainResolver::class));

    $mock->assertSent(fn (ListHistoryRequest $request): bool => $request->query()->get('startHistoryId') === '1000');

    expect($connection->refresh()->history_id)->toBe('1050');
});

it('does not create a duplicate event when the same mail is read again', function () {
    $connection = GmailConnection::factory()->create(['history_id' => '1000']);

    MockClient::global([
        ListHistoryRequest::class => MockResponse::make([
            'history' => [
                ['id' => '1001', 'messagesAdded' => [['message' => ['id' => 'message-1', 'threadId' => 'thread-1', 'labelIds' => ['SENT']]]]],
            ],
            'historyId' => '1050',
        ]),
        GetMessageRequest::class => MockResponse::make([
            'id' => 'message-1',
            'threadId' => 'thread-1',
            'labelIds' => ['SENT'],
            'internalDate' => '1793872800123',
            'payload' => ['headers' => []],
        ]),
    ]);

    new SyncUserMailJob($connection)->handle(app(OAuthService::class), app(CustomerEmailDomainResolver::class));
    new SyncUserMailJob($connection)->handle(app(OAuthService::class), app(CustomerEmailDomainResolver::class));

    expect(Event::count())->toBe(1);
});

it('starts reading from now on a first sync', function () {
    $connection = GmailConnection::factory()->create(['history_id' => null]);

    MockClient::global([
        GetProfileRequest::class => MockResponse::make(['emailAddress' => 'tomas@pionect.nl', 'historyId' => '2000']),
    ]);

    new SyncUserMailJob($connection)->handle(app(OAuthService::class), app(CustomerEmailDomainResolver::class));

    expect($connection->refresh()->history_id)->toBe('2000')
        ->and(Event::count())->toBe(0);
});

it('starts reading from now when gmail no longer knows the stored history id', function () {
    $connection = GmailConnection::factory()->create(['history_id' => '1000']);

    MockClient::global([
        ListHistoryRequest::class => MockResponse::make(['error' => ['code' => 404]], 404),
        GetProfileRequest::class => MockResponse::make(['emailAddress' => 'tomas@pionect.nl', 'historyId' => '2000']),
    ]);

    new SyncUserMailJob($connection)->handle(app(OAuthService::class), app(CustomerEmailDomainResolver::class));

    expect($connection->refresh()->history_id)->toBe('2000');
});

it('skips a message that is gone and still moves the history on', function () {
    $connection = GmailConnection::factory()->create(['history_id' => '1000']);

    MockClient::global([
        ListHistoryRequest::class => MockResponse::make([
            'history' => [
                ['id' => '1001', 'messagesAdded' => [['message' => ['id' => 'draft-message-1', 'threadId' => 'thread-1', 'labelIds' => ['DRAFT']]]]],
            ],
            'historyId' => '1050',
        ]),
        GetMessageRequest::class => MockResponse::make(['error' => ['code' => 404]], 404),
    ]);

    new SyncUserMailJob($connection)->handle(app(OAuthService::class), app(CustomerEmailDomainResolver::class));

    expect(Event::count())->toBe(0)
        ->and($connection->refresh()->history_id)->toBe('1050');
});

it('keeps the history id when a message cannot be read for now', function () {
    $connection = GmailConnection::factory()->create(['history_id' => '1000']);

    MockClient::global([
        ListHistoryRequest::class => MockResponse::make([
            'history' => [
                ['id' => '1001', 'messagesAdded' => [['message' => ['id' => 'message-1', 'threadId' => 'thread-1', 'labelIds' => ['SENT']]]]],
            ],
            'historyId' => '1050',
        ]),
        GetMessageRequest::class => MockResponse::make(['error' => ['code' => 429]], 429),
    ]);

    new SyncUserMailJob($connection)->handle(app(OAuthService::class), app(CustomerEmailDomainResolver::class));

    expect($connection->refresh()->history_id)->toBe('1000');
});
