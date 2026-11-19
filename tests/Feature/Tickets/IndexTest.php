<?php

use App\DataTransferObjects\Ticket;
use App\DataTransferObjects\TicketAction;
use App\Integrations\Tickets\TicketService;
use App\Models\Customer;
use App\Models\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Requests\RefreshTokenRequest;

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

it('returns no tickets instead of failing when jira no longer accepts the refresh token', function () {
    $this->loginUser();
    $integration = Integration::factory()->create(['type' => 'jira', 'config' => [
        'access_token' => 'an-access-token',
        'refresh_token' => 'a-revoked-refresh-token',
        'expires_at' => now()->subMinute()->timestamp,
        'cloud_id' => 'cloud-1',
        'cloud_url' => 'https://acme.atlassian.net',
    ]]);
    MockClient::global([
        RefreshTokenRequest::class => MockResponse::make(['error' => 'invalid_grant', 'error_description' => 'Unknown or invalid refresh token.'], 403),
    ]);

    $response = $this->get('tickets?filter[search]=DK-11');

    $response->assertSuccessful()->assertJson(['data' => []]);
    expect($integration->refresh()->config)->toBe([]);
    MockClient::destroyGlobal();
});
