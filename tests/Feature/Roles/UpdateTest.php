<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('renames a role', function () {
    $this->loginUser(permissions: ['roles.update']);
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);

    $this->patchJson("roles/{$role->id}", [
        'type' => 'roles',
        'attributes' => ['name' => 'controller'],
    ])->assertSuccessful()
        ->assertJsonPath('data.attributes.name', 'controller');
});
