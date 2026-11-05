<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('lists the permissions by id', function () {
    $user = $this->loginUser(permissions: ['permissions.read']);
    $permission = $user->permissions->firstOrFail();

    $this->getJson('permissions?page[size]=100')
        ->assertSuccessful()
        ->assertJsonFragment(['type' => 'permissions', 'id' => (string) $permission->id]);
});
