<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('syncs the roles and permissions of a user', function () {
    $this->loginUser(permissions: ['users.update']);
    $user = User::factory()->create();
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);

    $this->patchJson("users/{$user->id}", [
        'type' => 'users',
        'attributes' => ['givenName' => 'Ada'],
        'relationships' => [
            'roles' => ['data' => [['type' => 'roles', 'id' => (string) $role->id]]],
            'permissions' => ['data' => [['type' => 'permissions', 'id' => 'budgets.read']]],
        ],
    ])->assertSuccessful();

    expect($user->fresh()->hasRole('auditor'))->toBeTrue()
        ->and($user->fresh()->hasDirectPermission('budgets.read'))->toBeTrue();
});
