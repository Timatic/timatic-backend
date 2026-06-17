<?php

namespace App\Policies;

use App\Models\EntrySuggestion;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Config\Repository;

class EntrySuggestionPolicy
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
        return $user->can('entry-suggestions.read');
    }

    public function view(Authorizable $user, EntrySuggestion $entrySuggestion): bool
    {
        return $user->can('entry-suggestions.read');
    }

    public function create(Authorizable $user): bool
    {
        return false;
    }

    public function update(Authorizable $user, EntrySuggestion $entrySuggestion): bool
    {
        return false;
    }

    public function delete(Authorizable $user, EntrySuggestion $entrySuggestion): bool
    {
        return $user->can('entry-suggestions.delete');
    }

    public function restore(Authorizable $user, EntrySuggestion $entrySuggestion): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, EntrySuggestion $entrySuggestion): bool
    {
        return false;
    }
}
