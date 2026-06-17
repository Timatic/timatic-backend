<?php

use App\Models\ApiToken;
use App\Models\Entry;
use App\Models\Overtime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

test('can not approve own overtime', function () {
    Event::fake();

    /** @var User $user */
    $user = User::factory()->create();

    $credentials = [
        'user_id' => $user->external_id,
        'email' => 'test@timatic.app',
    ];
    $this->loginUser($credentials, ['user', 'overtimes.approve']);
    $overtimes = Overtime::factory()
        ->create([
            'entry_id' => Entry::factory()
                ->state([
                    'user_id' => $user->id,
                    'user_email' => 'test@timatic.app',
                ]),
        ]);

    $this->postJson(
        route('overtime.approve', ['overtime' => $overtimes->first()->id])
    )->assertStatus(403);
});

test('can approve others overtime', function () {
    Event::fake();

    /** @var User $user */
    $user = User::factory()->create(['email' => 'test@timatic.app']);

    $this->loginUser(['user_id' => $user->external_id], ['user', 'overtimes.approve']);

    /** @var User $overtimeUser */
    $overtimeUser = User::factory()->create();

    $overtimes = Overtime::factory()
        ->create([
            'entry_id' => Entry::factory()->state([
                'user_id' => $overtimeUser->id,
                'user_email' => $overtimeUser->email,
            ]),
        ]);

    $this->postJson(
        route('overtime.approve', ['overtime' => $overtimes->first()->id]),
    )->assertStatus(200);
});

test('an api token can not approve overtime', function () {
    /** @var ApiToken $token */
    $token = ApiToken::factory()->create();

    $token->givePermissionTo(['user', 'overtimes.approve']);

    Auth::setUser($token);

    Event::fake();

    $overtimes = Overtime::factory()->create();

    $this->postJson(
        route('overtime.approve', ['overtime' => $overtimes->first()->id]),
    )->assertForbidden();
});
