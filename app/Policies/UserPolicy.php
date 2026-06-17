<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(Authorizable $authUser): bool
    {
        return $authUser->can('users.read');
    }

    public function view(Authorizable $authUser): bool
    {
        return $authUser->can('users.read');
    }

    public function create(Authorizable $authUser): bool
    {
        return $authUser->can('users.create');
    }

    public function update(Authorizable $authUser): bool
    {
        return $authUser->can('users.update');
    }

    public function delete(Authorizable $authUser): bool
    {
        return $authUser->can('users.delete');
    }

    public function deleteAny(Authorizable $authUser): bool
    {
        return $authUser->can('users.delete');
    }

    public function restore(Authorizable $authUser): bool
    {
        return $authUser->can('users.restore');
    }

    public function forceDelete(Authorizable $authUser): bool
    {
        return $authUser->can('users.force-delete');
    }

    public function forceDeleteAny(Authorizable $authUser): bool
    {
        return $authUser->can('users.force-delete');
    }

    public function restoreAny(Authorizable $authUser): bool
    {
        return $authUser->can('users.restore');
    }

    public function replicate(Authorizable $authUser): bool
    {
        return $authUser->can('users.replicate');
    }

    public function reorder(Authorizable $authUser): bool
    {
        return $authUser->can('users.reorder');
    }
}
