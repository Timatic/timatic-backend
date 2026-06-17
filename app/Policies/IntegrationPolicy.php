<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Integration;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;

class IntegrationPolicy
{
    use HandlesAuthorization;

    public function viewAny(Authorizable $authUser): bool
    {
        return $authUser->can('integrations.read');
    }

    public function view(Authorizable $authUser, Integration $integration): bool
    {
        return $authUser->can('integrations.read');
    }

    public function create(Authorizable $authUser): bool
    {
        return $authUser->can('integrations.create');
    }

    public function update(Authorizable $authUser, Integration $integration): bool
    {
        return $authUser->can('integrations.update');
    }

    public function delete(Authorizable $authUser, Integration $integration): bool
    {
        return $authUser->can('integrations.delete');
    }

    public function deleteAny(Authorizable $authUser): bool
    {
        return $authUser->can('integrations.delete');
    }

    public function restore(Authorizable $authUser, Integration $integration): bool
    {
        return $authUser->can('integrations.restore');
    }

    public function forceDelete(Authorizable $authUser, Integration $integration): bool
    {
        return $authUser->can('integrations.force-delete');
    }

    public function forceDeleteAny(Authorizable $authUser): bool
    {
        return $authUser->can('integrations.force-delete');
    }

    public function restoreAny(Authorizable $authUser): bool
    {
        return $authUser->can('integrations.restore');
    }

    public function replicate(Authorizable $authUser, Integration $integration): bool
    {
        return $authUser->can('integrations.replicate');
    }

    public function reorder(Authorizable $authUser): bool
    {
        return $authUser->can('integrations.reorder');
    }
}
