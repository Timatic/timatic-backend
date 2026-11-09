<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    // Only admins see the roles page and its permissions; users still see their own roles through /me and /users
    public function up(): void
    {
        Role::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', 'admin')
            ->each(fn (Role $role) => $role->revokePermissionTo(['roles.read', 'permissions.read']));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
