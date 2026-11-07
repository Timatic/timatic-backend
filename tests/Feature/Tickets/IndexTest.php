<?php

use App\DataTransferObjects\Ticket;
use App\DataTransferObjects\TicketAction;
use App\Integrations\Tickets\TicketService;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);
uses(RefreshDatabase::class);

it('includes the customer and actions of the tickets', function () {
    $this->loginUser();

    $customer = Customer::factory()->create();

    $ticket = new Ticket(
        type: 'Bug',
        id: 'EB-389',
        number: 'EB-389',
        title: 'Login page broken',
        created_at: CarbonImmutable::parse('2024-01-15T10:30:00Z'),
        closed_at: null,
        url: 'https://example.atlassian.net/browse/EB-389',
        customer_id: $customer->id,
        actions: collect([new TicketAction(
            id: 'comment-42',
            entryDate: CarbonImmutable::parse('2024-01-15T11:00:00Z'),
            text: 'Fixed the issue.',
            personDisplayName: 'John Doe',
            personEmail: 'john.doe@example.com',
        )]),
    );

    $this->instance(TicketService::class, Mockery::mock(TicketService::class, function ($mock) use ($ticket) {
        $mock->shouldReceive('searchTickets')->andReturn(collect([$ticket]));
    }));

    $response = $this->get('tickets?include=customers,actions')->assertSuccessful();

    expect($response->json('data.0.relationships.customers.data.id'))->toBe((string) $customer->id)
        ->and($response->json('data.0.relationships.actions.data.0.id'))->toBe('comment-42')
        ->and(collect($response->json('included'))->map(fn (array $included) => $included['type'].':'.$included['id'])->all())
        ->toBe(['customers:'.$customer->id, 'actions:comment-42']);
});
