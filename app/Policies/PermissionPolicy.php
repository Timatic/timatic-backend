<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;

class PermissionPolicy
{
    use HandlesAuthorization;

    public function viewAny(Authorizable $user): bool
    {
        return $user->can('permissions.read');
    }

    public function create(Authorizable $user): bool
    {
        return $user->can('permissions.create');
    }
}
