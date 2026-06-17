<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Entry;
use App\Models\Overtime;
use App\Models\OvertimeType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

beforeEach(function () {
    Event::fake();
});

it('does not delete overtime when patched', function () {
    $this->markTestSkipped('This test is temporary disabled, because of timezone change bug');

    /** @var Entry $entry */
    $entry = Entry::factory()->create([
        'started_at' => Carbon::now()->startOfWeek()->setHour(16),
        'ended_at' => Carbon::now()->startOfWeek()->setHour(18),
    ]);

    /** @var Overtime $personalOvertime */
    $personalOvertime = Overtime::factory()->create([
        'entry_id' => $entry->id,
        'overtime_type_id' => OvertimeType::PERSONAL,
        'started_at' => Carbon::now()->startOfWeek()->setHour(17),
        'ended_at' => Carbon::now()->startOfWeek()->setHour(18),
    ]);

    /** @var Overtime $customerOvertime */
    $customerOvertime = Overtime::factory()->create([
        'entry_id' => $entry->id,
        'overtime_type_id' => OvertimeType::CUSTOMER,
        'started_at' => Carbon::now()->startOfWeek()->setHour(17),
        'ended_at' => Carbon::now()->startOfWeek()->setHour(18),
    ]);

    $this->patchJson('entries/'.$entry->id, [
        'data' => [
            'type' => 'entries',
            'attributes' => [
                'isInvoiced' => true,
            ],
        ],
    ])->assertSuccessful();

    $this->assertModelExists($personalOvertime);
    $this->assertModelExists($customerOvertime);

    expect($entry->personalOvertime?->started_at)->toEqual($entry->refresh()->started_at);

    expect($entry->personalOvertime?->ended_at)->toEqual($entry->refresh()->ended_at);
});

it('updates an entry by the owner when not locked', function () {
    /** @var User $user */
    $user = $this->loginUser(['user_id' => 'owner']);

    $budget = provisionBudget();

    $oldDescription = 'first version';

    $startedAt = Carbon::now();
    $endedAt = $startedAt->copy()->addHour();

    $entry = provisionEntry($budget, [
        'user_id' => $user->id,
        'description' => $oldDescription,
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
    ]);

    $description = 'second version';

    $this->patchJson('entries/'.$entry->id, [
        'type' => 'entries',
        'attributes' => [
            'description' => $description,
        ],
    ])->assertSuccessful();

    $entry->refresh();

    expect($entry->description)->toEqual($description);
});

it('denies to update an entry by the owner when 10 days have passed', function () {
    /** @var User $user */
    $user = $this->loginUser(['id' => 'owner']);

    $budget = provisionBudget();

    $oldDescription = 'first version';

    $startedAt = Carbon::now()->subDays(((int) config('timatic.entries_locked_after_days')) + 1);
    $endedAt = $startedAt->copy()->addHour();

    $entry = provisionEntry($budget, [
        'user_id' => $user->id,
        'description' => $oldDescription,
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
    ]);

    $description = 'second version';

    $this->patchJson('entries/'.$entry->id, [
        'type' => 'entries',
        'attributes' => [
            'description' => $description,
        ],
    ])->assertStatus(403);

    $entry->refresh();

    expect($entry->description)->toEqual($oldDescription);
});

it('denies to update an entry by someone other than the owner', function () {
    $this->loginUser(['email' => $this->faker->companyEmail()]);

    $budget = provisionBudget();

    $oldDescription = 'first version';

    $entry = provisionEntry($budget, [
        'description' => $oldDescription,
        'started_at' => Carbon::now()->subMonths(2),
        'ended_at' => Carbon::now()->subMonths(2)->addHour(),
    ]);

    $description = 'second version';

    $this->patchJson('entries/'.$entry->id, [
        'type' => 'entries',
        'attributes' => [
            'description' => $description,
        ],
    ])->assertStatus(403);

    $entry->refresh();

    expect($entry->description)->toEqual($oldDescription);
});

it('updates a month old entry when edited with proper permissions', function () {
    $this->travelTo(Carbon::today()->startOfMonth());

    /** @var User $user */
    $user = $this->loginUser(['email' => $this->faker->companyEmail()], ['user', 'entries.update_from_previous_month']);

    $budget = provisionBudget();

    $oldDescription = 'first version';

    $entry = provisionEntry($budget, [
        'user_id' => $user->id,
        'description' => $oldDescription,
        'started_at' => Carbon::now()->subMonth(),
        'ended_at' => Carbon::now()->subMonth()->addHour(),
    ]);

    $description = 'second version';

    $this->patchJson('entries/'.$entry->id, [
        'type' => 'entries',
        'attributes' => [
            'description' => $description,
        ],
    ])->assertSuccessful();

    $entry->refresh();

    expect($entry->description)->toEqual($description);
});

it('denies to update a two month old entry when edited with proper permissions', function () {
    /** @var User $user */
    $user = $this->loginUser(['email' => $this->faker->companyEmail()]);

    config()->set('permissions.edit_entries_from_previous_month', [$user->email]);

    $budget = provisionBudget();

    $oldDescription = 'first version';

    $entry = provisionEntry($budget, [
        'user_id' => $user->id,
        'description' => $oldDescription,
        'started_at' => Carbon::now()->subMonths(2),
        'ended_at' => Carbon::now()->subMonths(2)->addHour(),
    ]);

    $description = 'second version';

    $this->patchJson('entries/'.$entry->id, [
        'type' => 'entries',
        'attributes' => [
            'description' => $description,
        ],
    ])->assertStatus(403);

    $entry->refresh();

    expect($entry->description)->toEqual($oldDescription);
});

it('unsets the budget id when entry gets edited to internal', function () {
    /** @var User $user */
    $user = $this->loginUser(['email' => $this->faker->companyEmail()]);

    $budget = provisionBudget();

    $entry = provisionEntry($budget, [
        'user_id' => $user->id,
        'started_at' => Carbon::now(),
        'ended_at' => Carbon::now()->addHour(),
    ]);

    $this->patchJson('entries/'.$entry->id, [
        'type' => 'entries',
        'attributes' => [
            'isInternal' => true,
        ],
    ])->assertSuccessful();

    $entry->refresh();

    expect($entry->budget_id)->toBeNull();
});

function provisionBudget(): Budget
{
    $yearAgo = Carbon::now()->subYear();

    /** @var Budget $budget */
    $budget = Budget::factory()->create([
        'started_at' => $yearAgo,
        'ended_at' => Carbon::now()->addYear(),
    ]);

    BudgetVersion::factory()->create([
        'budget_id' => $budget->id,
        'effective_from' => $yearAgo,
    ]);

    return $budget;
}

/**
 * @param  array<string, mixed>  $attributes
 *
 * @throws Exception
 */
function provisionEntry(Budget $budget, array $attributes = []): Entry
{
    /** @var Entry $entry */
    $entry = Entry::factory()->create(array_merge([
        'budget_id' => $budget->id,
        'started_at' => Carbon::now()->subMonth(),
        'ended_at' => Carbon::now()->subMonth()->addHour(),
        'description' => 'first version', // Be 100% sure we won't update it to what it was before
    ], $attributes));

    $entry->overtimes->each(fn (Overtime $overtime) => $overtime->delete());
    $entry->unsetRelations();

    return $entry;
}
