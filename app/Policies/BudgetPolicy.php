<?php

namespace App\Policies;

use App\Models\Budget;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Config\Repository;

class BudgetPolicy
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
        return $user->can('budgets.read');
    }

    public function view(Authorizable $user, Budget $budget): bool
    {
        return $user->can('budgets.read');
    }

    public function create(Authorizable $user): bool
    {
        return $user->can('budgets.create');
    }

    public function update(Authorizable $user, Budget $budget): bool
    {
        return $user->can('budgets.update');
    }

    public function delete(Authorizable $user, Budget $budget): bool
    {
        return false;
    }
}
