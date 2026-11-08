<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('includes a permission shared by several roles once', function () {
    $this->loginUser(permissions: ['roles.read']);

    Role::query()->delete();
    Role::create(['name' => 'auditor', 'guard_name' => 'web'])->givePermissionTo('budgets.read');
    Role::create(['name' => 'controller', 'guard_name' => 'web'])->givePermissionTo('budgets.read');

    $response = $this->getJson('roles?include=permissions')->assertSuccessful();

    expect(collect($response->json('included'))->pluck('id')->all())->toBe(['budgets.read']);
});

it('counts the users of a role', function () {
    $this->loginUser(permissions: ['roles.read']);

    Role::query()->delete();
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    User::factory()->count(2)->create()->each->assignRole($role);

    $this->getJson('roles')
        ->assertSuccessful()
        ->assertJsonPath('data.0.attributes.usersCount', 2);
});
