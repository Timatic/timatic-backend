<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    use HandlesAuthorization;

    public function viewAny(Authorizable $user): bool
    {
        return $user->can('permissions.read');
    }

    public function view(Authorizable $user, Permission $permission): bool
    {
        return $user->can('permissions.read');
    }

    public function create(Authorizable $user): bool
    {
        return $user->can('permissions.create');
    }

    public function update(Authorizable $user, Permission $permission): bool
    {
        return $user->can('permissions.update');
    }

    public function delete(Authorizable $user, Permission $permission): bool
    {
        return $user->can('permissions.delete');
    }
}
