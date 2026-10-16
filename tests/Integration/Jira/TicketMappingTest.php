<?php

use App\Models\Budget;
use App\Models\Customer;
use App\Models\Event as TimeEvent;
use App\Models\EventType;
use App\Models\Integration;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Models\ProjectMapping;

uses(LoginUser::class);

/**
 * @return array<string, mixed>
 */
function eventWithTicket(array $attributes): array
{
    return [
        'data' => [
            'type' => 'events',
            'attributes' => [
                'sourceId' => Source::factory()->create()->id,
                'eventTypeId' => EventType::factory()->create()->id,
                'userId' => User::factory()->create()->id,
                'endedAt' => Carbon::now()->subMinute()->toIso8601String(),
                ...$attributes,
            ],
        ],
    ];
}

function mapJiraProject(string $projectKey, ?Customer $customer, ?Budget $budget): void
{
    $integration = Integration::factory()->create(['type' => 'jira']);

    ProjectMapping::query()->create([
        'integration_id' => $integration->id,
        'project_key' => $projectKey,
        'project_name' => $projectKey,
        'customer_id' => $customer?->id,
        'budget_id' => $budget?->id,
        'is_archived' => false,
    ]);
}

it('books an event on the customer and budget its ticket is mapped to', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    /** @var Budget $mappedBudget */
    $mappedBudget = Budget::factory()->create();
    mapJiraProject('ACME', $mappedBudget->customer, $mappedBudget);

    /** @var Budget $sentBudget */
    $sentBudget = Budget::factory()->create();

    $this->postJson(route('events.store'), eventWithTicket([
        'ticketNumber' => 'ACME-12',
        'customerId' => $sentBudget->customer_id,
        'budgetId' => $sentBudget->id,
    ]))->assertCreated();

    $event = TimeEvent::query()->sole();

    expect((int) $event->customer_id)->toBe($mappedBudget->customer_id)
        ->and($event->budget_id)->toBe($mappedBudget->id);
});

it('drops a budget of another customer than the ticket is mapped to', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    /** @var Customer $mappedCustomer */
    $mappedCustomer = Customer::factory()->create();
    mapJiraProject('ACME', $mappedCustomer, null);

    /** @var Budget $sentBudget */
    $sentBudget = Budget::factory()->create();

    $this->postJson(route('events.store'), eventWithTicket([
        'ticketNumber' => 'ACME-12',
        'customerId' => $sentBudget->customer_id,
        'budgetId' => $sentBudget->id,
    ]))->assertCreated();

    $event = TimeEvent::query()->sole();

    expect((int) $event->customer_id)->toBe($mappedCustomer->id)
        ->and($event->budget_id)->toBeNull();
});

it('keeps the budget sent when it belongs to the customer the ticket is mapped to', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    /** @var Budget $sentBudget */
    $sentBudget = Budget::factory()->create();
    mapJiraProject('ACME', $sentBudget->customer, null);

    $this->postJson(route('events.store'), eventWithTicket([
        'ticketNumber' => 'ACME-12',
        'customerId' => $sentBudget->customer_id,
        'budgetId' => $sentBudget->id,
    ]))->assertCreated();

    expect(TimeEvent::query()->sole()->budget_id)->toBe($sentBudget->id);
});

it('keeps what the client sent for a ticket that is not mapped', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    mapJiraProject('ACME', Customer::factory()->create(), null);

    /** @var Budget $sentBudget */
    $sentBudget = Budget::factory()->create();

    $this->postJson(route('events.store'), eventWithTicket([
        'ticketNumber' => 'OTHER-12',
        'customerId' => $sentBudget->customer_id,
        'budgetId' => $sentBudget->id,
    ]))->assertCreated();

    $event = TimeEvent::query()->sole();

    expect((int) $event->customer_id)->toBe($sentBudget->customer_id)
        ->and($event->budget_id)->toBe($sentBudget->id);
});

it('ignores the mapping of an archived project', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    mapJiraProject('ACME', Customer::factory()->create(), null);
    ProjectMapping::query()->update(['is_archived' => true]);

    /** @var Customer $sentCustomer */
    $sentCustomer = Customer::factory()->create();

    $this->postJson(route('events.store'), eventWithTicket([
        'ticketNumber' => 'ACME-12',
        'customerId' => $sentCustomer->id,
    ]))->assertCreated();

    expect((int) TimeEvent::query()->sole()->customer_id)->toBe($sentCustomer->id);
});
