<?php

use App\Models\BudgetVersion;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

beforeEach()->loginUser(permissions: ['entries.read']);

it('returns a collection response', function () {
    Event::fake();

    $entry = Entry::factory()->create();

    $this->getJson('entries')
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                ['type' => 'entries'],
            ],
        ]);
});

it('returns a collection response with budget included by default', function () {
    Event::fake();

    $entry = Entry::factory()->create();

    assert($entry instanceof Entry);

    $version = BudgetVersion::factory()->create();

    assert($version instanceof BudgetVersion);

    $entry->budget_id = $version->budget_id;
    $entry->save();

    $this->getJson('entries?include=budget')
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                ['type' => 'entries'],
            ],
            'included' => [
                ['id' => (string) $entry->budget_id, 'type' => 'budgets'],
            ],
        ]);
});

it('returns paid per hour when no budget is set', function () {
    Event::fake();

    $entry = Entry::factory()->create();
    $entry->save();

    $this->getJson('entries')
        ->assertSuccessful()
        ->assertJsonPath('data.0.attributes.isPaidPerHour', true);
});

it('returns not paid per hour when budget is set', function () {
    Event::fake();

    $entry = Entry::factory()->create();

    $version = BudgetVersion::factory()->create();
    $entry->budget_id = $version->budget_id;
    $entry->save();

    $this->getJson('entries')
        ->assertSuccessful()
        ->assertJsonPath('data.0.attributes.isPaidPerHour', false);
});

it('does not return paid per hour when entry is marked as internal', function () {
    Event::fake();

    $entry = Entry::factory()->create([
        'is_internal' => true,
    ]);

    $version = BudgetVersion::factory()->create();
    $entry->budget_id = $version->budget_id;
    $entry->save();

    $this->getJson('entries')
        ->assertSuccessful()
        ->assertJsonPath('data.0.attributes.isPaidPerHour', false);
});
