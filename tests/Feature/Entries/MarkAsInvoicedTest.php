<?php

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\LoginUser;

use function Pest\Laravel\post;

uses(
    RefreshDatabase::class,
    LoginUser::class,
);

beforeEach(fn () => Event::fake());

it('sets an entry to invoiced if it has no budget id and is not internal', function () {
    $this->loginUser(permissions: ['entries.mark-as-invoiced']);

    $entry = Entry::factory()->create([
        'budget_id' => null,
        'is_internal' => false,
    ]);

    post('/entries/'.$entry->id.'/mark-as-invoiced')
        ->assertSuccessful();

    expect(
        $entry->refresh()->invoiced_at
    )->not()->toBeNull();
});

it('receives forbidden response if mark as invoiced permission is not present', function () {
    $this->loginUser();

    $entry = Entry::factory()->create([
        'budget_id' => null,
        'is_internal' => false,
    ]);

    post('/entries/'.$entry->id.'/mark-as-invoiced')
        ->assertForbidden();
});

it('does not allow to invoice an internal entry', function () {
    $this->loginUser(permissions: ['entries.mark-as-invoiced']);

    $entry = Entry::factory()->create([
        'budget_id' => null,
        'is_internal' => true,
    ]);

    post('/entries/'.$entry->id.'/mark-as-invoiced')
        ->assertUnprocessable();
});
