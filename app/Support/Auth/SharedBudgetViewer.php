<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\Budget;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Whoever opens a budget's share link: authenticated by the share token, not by an account.
 */
class SharedBudgetViewer extends GenericUser implements Authorizable
{
    public function __construct(public readonly Budget $budget)
    {
        parent::__construct(['id' => 'share-token:'.$budget->id]);
    }

    /**
     * Grants only what the budget policies ask for; RestrictShareTokenToSharedBudget limits the routes to this budget.
     *
     * @param  iterable<string>|string  $abilities
     * @param  array<mixed>|mixed  $arguments
     */
    public function can($abilities, $arguments = []): bool
    {
        return $abilities === 'budgets.read';
    }

    /**
     * Filament Shield's super admin gate asks every principal for its role.
     */
    public function hasRole(string $role): bool
    {
        return false;
    }
}
