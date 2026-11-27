<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('deletes a role nobody has', function () {
    $this->loginUser(permissions: ['roles.delete']);
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);

    $this->deleteJson("roles/{$role->id}")->assertNoContent();

    $this->assertModelMissing($role);
});

it('refuses to delete a role that is still assigned', function () {
    $this->loginUser(permissions: ['roles.delete']);
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($role);

    $this->deleteJson("roles/{$role->id}")->assertConflict();

    $this->assertModelExists($role);
});

it('refuses to delete the admin role', function () {
    $this->loginUser(permissions: ['roles.delete']);
    $role = Role::findOrCreate('admin', 'web');

    $this->deleteJson("roles/{$role->id}")->assertForbidden();
});
