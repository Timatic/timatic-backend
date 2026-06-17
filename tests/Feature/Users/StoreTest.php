<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('can not create a user without a unique email', function () {
    $this->loginUser(permissions: ['users.create']);

    /** @var User $userOne */
    $userOne = User::factory()->create(['email' => $this->faker->email()]);

    $this->postJson('users', [
        'type' => 'users',
        'attributes' => [
            'name' => $this->faker->name(),
            'externalId' => $this->faker->uuid(),
            'email' => $userOne->email,
        ],
    ])->assertStatus(422);
});
