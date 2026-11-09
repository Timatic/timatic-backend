<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('lists the permissions by name', function () {
    $this->loginUser(permissions: ['permissions.read']);

    $this->getJson('permissions?page[size]=100')
        ->assertSuccessful()
        ->assertJsonFragment(['type' => 'permissions', 'id' => 'permissions.read']);
});
