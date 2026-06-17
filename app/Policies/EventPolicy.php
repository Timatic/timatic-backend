<?php

namespace App\Policies;

use App\Models\Event;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Config\Repository;

class EventPolicy
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

    public function view(Authorizable $user, Event $event): bool
    {
        return $user->can('events.read');
    }

    public function create(Authorizable $user): bool
    {
        return $user->can('events.create');
    }

    public function update(Authorizable $user, Event $event): bool
    {
        return $user->can('events.update');
    }

    public function delete(Authorizable $user, Event $event): bool
    {
        return $user->can('events.delete');
    }

    public function restore(Authorizable $user, Event $event): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, Event $event): bool
    {
        return false;
    }
}
