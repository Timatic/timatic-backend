<?php

use App\Models\Overtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\LoginUser;

use function Pest\Laravel\post;

uses(
    RefreshDatabase::class,
    LoginUser::class,
);

beforeEach(fn () => Event::fake());

it('sets an overtime to exported', function () {
    $this->loginUser(permissions: ['overtimes.mark-as-exported']);

    /** @var Overtime $overtime */
    $overtime = Overtime::factory()->create(['exported_at' => null]);

    post('/overtimes/'.$overtime->id.'/mark-as-exported')
        ->assertSuccessful();

    expect(
        $overtime->refresh()->exported_at
    )->not()->toBeNull();
});

it('receives forbidden response if mark as exported permission is not present', function () {
    $this->loginUser();

    /** @var Overtime $overtime */
    $overtime = Overtime::factory()->create(['exported_at' => null]);

    post('/overtimes/'.$overtime->id.'/mark-as-exported')
        ->assertForbidden();
});
