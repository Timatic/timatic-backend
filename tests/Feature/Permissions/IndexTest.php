<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('lists the permissions by name', function () {
    $this->loginUser(permissions: ['permissions.read']);

    $this->getJson('permissions?page[size]=100')
        ->assertSuccessful()
        ->assertJsonFragment(['type' => 'permissions', 'id' => 'permissions.read']);
});

it('leaves out the permissions filament shield generated', function () {
    $this->loginUser(permissions: ['permissions.read']);
    Permission::create(['name' => 'view-any.customer', 'guard_name' => 'web']);

    $this->getJson('permissions?page[size]=100')
        ->assertSuccessful()
        ->assertJsonMissing(['id' => 'view-any.customer']);
});
