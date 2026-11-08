<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('lets every existing role read the admin section', function () {
    $role = Role::create(['name' => 'reviewer', 'guard_name' => 'web']);

    (require database_path('migrations/2026_11_08_000000_grant_admin_section_read_permissions_to_every_role.php'))->up();

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['customers.read', 'permissions.read', 'roles.read', 'users.read']);
});
