<?php

namespace App\Policies;

use App\Models\BudgetType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Config\Repository;

class BudgetTypePolicy
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
        return $user->can('budget-types.read');
    }

    public function view(Authorizable $user, BudgetType $budgetType): bool
    {
        return false;
    }

    public function create(Authorizable $user): bool
    {
        return false;
    }

    public function update(Authorizable $user, BudgetType $budgetType): bool
    {
        return false;
    }

    public function delete(Authorizable $user, BudgetType $budgetType): bool
    {
        return false;
    }

    public function restore(Authorizable $user, BudgetType $budgetType): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, BudgetType $budgetType): bool
    {
        return false;
    }
}
