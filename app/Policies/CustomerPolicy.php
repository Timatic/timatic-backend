<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;

class CustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny(Authorizable $authUser): bool
    {
        return $authUser->can('customers.read');
    }

    public function view(Authorizable $authUser, Customer $customer): bool
    {
        return $authUser->can('customers.read');
    }

    public function create(Authorizable $authUser): bool
    {
        return $authUser->can('customers.create');
    }

    public function update(Authorizable $authUser, Customer $customer): bool
    {
        return $authUser->can('customers.update');
    }

    public function delete(Authorizable $authUser, Customer $customer): bool
    {
        return $authUser->can('customers.delete');
    }

    public function deleteAny(Authorizable $authUser): bool
    {
        return $authUser->can('customers.delete');
    }

    public function restore(Authorizable $authUser, Customer $customer): bool
    {
        return $authUser->can('customers.restore');
    }

    public function forceDelete(Authorizable $authUser, Customer $customer): bool
    {
        return $authUser->can('customers.force-delete');
    }

    public function forceDeleteAny(Authorizable $authUser): bool
    {
        return $authUser->can('customers.force-delete');
    }

    public function restoreAny(Authorizable $authUser): bool
    {
        return $authUser->can('customers.restore');
    }

    public function replicate(Authorizable $authUser, Customer $customer): bool
    {
        return $authUser->can('customers.replicate');
    }

    public function reorder(Authorizable $authUser): bool
    {
        return $authUser->can('customers.reorder');
    }
}
