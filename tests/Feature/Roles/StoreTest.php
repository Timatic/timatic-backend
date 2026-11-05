<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('creates a role with its permissions', function () {
    $this->loginUser(permissions: ['roles.create']);
    $permission = Permission::findByName('budgets.read');

    $this->postJson('roles', [
        'type' => 'roles',
        'attributes' => ['name' => 'auditor'],
        'relationships' => [
            'permissions' => ['data' => [['type' => 'permissions', 'id' => (string) $permission->id]]],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.attributes.name', 'auditor');

    expect(Role::findByName('auditor', 'web')->hasPermissionTo('budgets.read'))->toBeTrue();
});
