<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('creates a role with its permissions', function () {
    $this->loginUser(permissions: ['roles.create']);

    $this->postJson('roles', [
        'type' => 'roles',
        'attributes' => ['name' => 'auditor'],
        'relationships' => [
            'permissions' => ['data' => [['type' => 'permissions', 'id' => 'budgets.read']]],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.id', (string) Role::findByName('auditor', 'web')->id)
        ->assertJsonPath('data.attributes.name', 'auditor');

    expect(Role::findByName('auditor', 'web')->hasPermissionTo('budgets.read'))->toBeTrue();
});
