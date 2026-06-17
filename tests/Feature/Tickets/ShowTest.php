<?php

use App\DataTransferObjects\Ticket;
use App\DataTransferObjects\TicketAction;
use App\Integrations\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);
uses(RefreshDatabase::class);

it('returns ticket details with actions', function () {
    $this->loginUser();

    $action = new TicketAction(
        id: 'comment-42',
        entryDate: CarbonImmutable::parse('2024-01-15T11:00:00Z'),
        text: 'Fixed the issue.',
        personDisplayName: 'John Doe',
        personEmail: 'john.doe@example.com',
    );

    $ticket = new Ticket(
        type: 'Bug',
        id: 'EB-389',
        number: 'EB-389',
        title: 'Login page broken',
        created_at: CarbonImmutable::parse('2024-01-15T10:30:00Z'),
        closed_at: null,
        url: 'https://example.atlassian.net/browse/EB-389',
        actions: collect([$action]),
    );

    $this->instance(TicketService::class, Mockery::mock(TicketService::class, function ($mock) use ($ticket) {
        $mock->shouldReceive('fetchTicketDetails')->with('EB-389')->andReturn($ticket);
    }));

    $response = $this->get('tickets/EB-389?include=actions')->assertSuccessful();

    expect($response->json('data.id'))->toBe('EB-389')
        ->and($response->json('data.type'))->toBe('tickets')
        ->and($response->json('data.attributes.title'))->toBe('Login page broken')
        ->and($response->json('data.attributes.createdAt'))->toBe('2024-01-15T10:30:00.000000Z')
        ->and($response->json('data.relationships.actions.data.0.id'))->toBe('comment-42');

    $actionIncluded = collect($response->json('included'))->firstWhere('type', 'actions');

    expect($actionIncluded['attributes']['text'])->toBe('Fixed the issue.')
        ->and($actionIncluded['attributes']['personDisplayName'])->toBe('John Doe')
        ->and($actionIncluded['attributes']['personEmail'])->toBe('john.doe@example.com');
});

it('returns 404 when the ticket is not found', function () {
    $this->loginUser();

    $this->instance(TicketService::class, Mockery::mock(TicketService::class, function ($mock) {
        $mock->shouldReceive('fetchTicketDetails')->with('UNKNOWN-1')->andReturn(null);
    }));

    $this->get('tickets/UNKNOWN-1')->assertNotFound();
});
