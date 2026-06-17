<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(Authorizable $authUser): bool
    {
        return $authUser->can('roles.read');
    }

    public function view(Authorizable $authUser, Role $role): bool
    {
        return $authUser->can('roles.read');
    }

    public function create(Authorizable $authUser): bool
    {
        return $authUser->can('roles.create');
    }

    public function update(Authorizable $authUser, Role $role): bool
    {
        return $authUser->can('roles.update');
    }

    public function delete(Authorizable $authUser, Role $role): bool
    {
        return $authUser->can('roles.delete');
    }

    public function deleteAny(Authorizable $authUser): bool
    {
        return $authUser->can('roles.delete');
    }

    public function restore(Authorizable $authUser, Role $role): bool
    {
        return $authUser->can('roles.restore');
    }

    public function forceDelete(Authorizable $authUser, Role $role): bool
    {
        return $authUser->can('roles.force-delete');
    }

    public function forceDeleteAny(Authorizable $authUser): bool
    {
        return $authUser->can('roles.force-delete');
    }

    public function restoreAny(Authorizable $authUser): bool
    {
        return $authUser->can('roles.restore');
    }

    public function replicate(Authorizable $authUser, Role $role): bool
    {
        return $authUser->can('roles.replicate');
    }

    public function reorder(Authorizable $authUser): bool
    {
        return $authUser->can('roles.reorder');
    }
}
