<?php

use App\Models\BudgetVersion;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach()->loginUser(permissions: ['user']);

it('serves user customer hours aggregates', function () {
    Event::fake();

    /** @var BudgetVersion $version */
    $version = BudgetVersion::factory()->create();
    assert(! is_null($version->budget));

    $budgetEntries = Entry::factory()->count(5)->create([
        'budget_id' => $version->budget->id,
    ]);

    $budgetMinutes = $budgetEntries->sum(fn ($entry) => (int) $entry->started_at->diffInMinutes($entry->ended_at), true);

    $paidPerHourEntries = Entry::factory()->count(5)->create();

    $paidPerHourMinutes = $paidPerHourEntries->sum(fn ($entry) => (int) $entry->started_at->diffInMinutes($entry->ended_at, true));

    $internalEntries = Entry::factory()->count(5)->create([
        'budget_id' => $version->budget->id,
        'is_internal' => true,
    ]);

    $internalMinutes = $internalEntries->sum(fn ($entry) => (int) $entry->started_at->diffInMinutes($entry->ended_at, true));

    $this->get('user-customer-hours-aggregates')
        ->assertSuccessful()
        ->assertJson([
            'meta' => [
                'totalInternalMinutes' => $internalMinutes,
                'totalBudgetMinutes' => $budgetMinutes,
                'totalPaidPerHourMinutes' => $paidPerHourMinutes,
            ],
        ])
        ->assertJsonCount(15, 'data')
        ->assertJsonStructure([
            'data' => [[
                'id',
                'type',
                'attributes' => [
                    'customerId',
                    'userId',
                    'internalMinutes',
                    'budgetMinutes',
                    'paidPerHourMinutes',
                ],
            ]],
        ]);
});
