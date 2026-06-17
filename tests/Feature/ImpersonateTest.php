<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

test('a user can impersonate another user with the correct permission', function () {
    /** @var User $user */
    $user = User::factory()->create();

    /** @var User $impersonatingUser */
    $impersonatingUser = User::factory()->create();

    $this->loginUser([
        'user_id' => $impersonatingUser->external_id,
    ], ['user', 'users.impersonate']);

    $this->get('/me', ['impersonate-user-id' => $user->id])
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                'id' => $user->id,
            ],
        ]);
});
