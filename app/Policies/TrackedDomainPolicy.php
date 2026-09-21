<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TrackedDomain;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;

class TrackedDomainPolicy
{
    use HandlesAuthorization;

    public function viewAny(Authorizable $authUser): bool
    {
        return $authUser->can('tracked-domains.read');
    }

    public function view(Authorizable $authUser, TrackedDomain $trackedDomain): bool
    {
        return $authUser->can('tracked-domains.read');
    }

    public function create(Authorizable $authUser): bool
    {
        return $authUser->can('tracked-domains.create');
    }

    /** Its owner always may: a private mapping is theirs alone. */
    public function update(Authorizable $authUser, TrackedDomain $trackedDomain): bool
    {
        return $this->owns($authUser, $trackedDomain) || $authUser->can('tracked-domains.update');
    }

    public function delete(Authorizable $authUser, TrackedDomain $trackedDomain): bool
    {
        return $this->owns($authUser, $trackedDomain) || $authUser->can('tracked-domains.delete');
    }

    public function deleteAny(Authorizable $authUser): bool
    {
        return $authUser->can('tracked-domains.delete');
    }

    private function owns(Authorizable $authUser, TrackedDomain $trackedDomain): bool
    {
        return $trackedDomain->user_id !== null
            && $authUser instanceof User
            && $trackedDomain->user_id === $authUser->id;
    }
}
