<?php

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
