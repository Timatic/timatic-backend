<?php

namespace App\Policies;

use App\Models\Overtime;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Access\Authorizable;

class OvertimePolicy
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

    public function viewAll(Authorizable $user): bool
    {
        return $user->can('overtimes.read');
    }

    public function approve(Authorizable $user, Overtime $overtime): bool
    {
        if (! $user instanceof User || $user->cannot('overtimes.approve')) {
            return false;
        }

        return $overtime->entry?->user_id != $user->id;
    }
}
