<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('returns a collection response', function () {
    $this->loginUser(permissions: ['users.read']);

    $user = User::factory()->create();

    $this->getJson('users')
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                ['type' => 'users'],
            ],
        ]);
});
