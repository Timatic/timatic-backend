<?php

use App\Integrations\TicketService;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Timatic\Bitbucket\Jobs\ProcessWebhookJob;
use Timatic\Bitbucket\Models\RepositoryMapping;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function prPayload(string $accountId, string $title = 'PROJ-1 Add feature'): array
{
    return [
        'actor' => [
            'account_id' => $accountId,
            'display_name' => 'Test User',
        ],
        'pullrequest' => [
            'id' => 1,
            'title' => $title,
            'source' => ['branch' => ['name' => 'feature/test']],
            'updated_on' => '2026-04-14T10:00:00+00:00',
        ],
        'repository' => [
            'full_name' => 'workspace/repo',
        ],
    ];
}

function createMappingWithCustomer(): RepositoryMapping
{
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $integration = Integration::create(['name' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);

    return RepositoryMapping::create([
        'integration_id' => $integration->id,
        'workspace_slug' => 'workspace',
        'repository_slug' => 'repo',
        'repository_name' => 'repo',
        'customer_id' => $customer->id,
        'budget_id' => null,
    ]);
}

it('creates an event when a PR is approved by a known user', function () {
    EventFacade::fake();

    /** @var User $user */
    $user = User::factory()->create(['bitbucket_account_id' => 'bb-account-123']);
    $mapping = createMappingWithCustomer();

    new ProcessWebhookJob(prPayload('bb-account-123'), $mapping, 'pullrequest:approved')->handle(app(TicketService::class));

    expect(Event::where('user_id', $user->id)->where('event_type_id', 'pr_approved')->exists())->toBeTrue();
});

it('creates the correct event type for each PR event key', function (string $eventKey, string $expectedEventTypeId) {
    EventFacade::fake();

    /** @var User $user */
    $user = User::factory()->create(['bitbucket_account_id' => 'bb-account-456']);
    $mapping = createMappingWithCustomer();

    new ProcessWebhookJob(prPayload('bb-account-456'), $mapping, $eventKey)->handle(app(TicketService::class));

    expect(Event::where('user_id', $user->id)->where('event_type_id', $expectedEventTypeId)->exists())->toBeTrue();
})->with([
    ['pullrequest:comment_created', 'pr_commented'],
    ['pullrequest:changes_request_created', 'pr_changes_requested'],
    ['pullrequest:fulfilled', 'pr_merged'],
    ['pullrequest:rejected', 'pr_declined'],
]);

it('does not create an event when the actor account id is unknown', function () {
    EventFacade::fake();

    User::factory()->create(['bitbucket_account_id' => 'bb-account-known']);
    $mapping = createMappingWithCustomer();

    new ProcessWebhookJob(prPayload('bb-account-unknown'), $mapping, 'pullrequest:approved')->handle(app(TicketService::class));

    expect(Event::count())->toBe(0);
});

it('does not create an event when there is no customer and no ticket', function () {
    EventFacade::fake();

    User::factory()->create(['bitbucket_account_id' => 'bb-account-789']);

    new ProcessWebhookJob(prPayload('bb-account-789'), null, 'pullrequest:approved')->handle(app(TicketService::class));

    expect(Event::count())->toBe(0);
});
