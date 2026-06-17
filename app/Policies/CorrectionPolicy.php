<?php

namespace App\Policies;

use App\Models\Correction;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Config\Repository;

class CorrectionPolicy
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
        return false;
    }

    public function view(Authorizable $user, Correction $correction): bool
    {
        return false;
    }

    public function create(Authorizable $user): bool
    {
        return $user->can('corrections.create');
    }

    public function update(Authorizable $user, Correction $correction): bool
    {
        return $user->can('corrections.update');
    }
}
