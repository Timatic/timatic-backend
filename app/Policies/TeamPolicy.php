<?php

namespace App\Policies;

use App\Models\Team;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Config\Repository;

class TeamPolicy
{
    use HandlesAuthorization;

    protected Repository $config;

    /**
     * Create a new policy instance.
     */
    public function __construct(Repository $config)
    {
        $this->config = $config;
    }

    public function viewAny(Authorizable $user): bool
    {
        return $user->can('teams.read');
    }

    public function view(Authorizable $user, Team $team): bool
    {
        return $user->can('teams.read');
    }

    public function create(Authorizable $user): bool
    {
        return $user->can('teams.create');
    }

    public function update(Authorizable $user, Team $team): bool
    {
        return $user->can('teams.update');
    }

    public function delete(Authorizable $user, Team $team): bool
    {
        return $user->can('teams.delete');
    }
}
