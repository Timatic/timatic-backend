<?php

namespace App\Policies;

use App\Models\ApiToken;
use App\Models\Budget;
use App\Models\Entry;
use App\Models\User;
use App\Services\EntryLockedDateService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Access\Authorizable;

class EntryPolicy
{
    use HandlesAuthorization;

    public function __construct(
        protected EntryLockedDateService $lockedDateService,
        protected Repository $config
    ) {}

    public function viewAny(Authorizable $user): bool
    {
        return $user->can('entries.read');
    }

    public function view(Authorizable $user, Entry $entry): bool
    {
        return $user->can('entries.read');
    }

    public function create(Authorizable $user): bool
    {
        return true;
    }

    public function creating(Authorizable $user, Entry $entry): bool
    {
        return $this->userCanCreate($user, $entry);
    }

    public function update(Authorizable $user, Entry $entry): bool
    {
        return $this->userCanModify($user, $entry);
    }

    public function delete(Authorizable $user, Entry $entry): bool
    {
        return $this->userCanModify($user, $entry);
    }

    protected function userCanModify(Authorizable $user, Entry $entry): bool
    {
        // No one can edit an entry that is locked with the isLocked property
        if ($entry->is_locked) {
            return false;
        }

        if ($this->lockedDateService->get()->isAfter($entry->started_at)) {
            return false;
        }

        if ($user->can('entries.update_from_others') ||
            ($user instanceof User && $this->currentUserIsOwnerOf($user, $entry))
        ) {
            return true;
        }

        if ($user instanceof User && $this->currentUserIsCreatorOf($user, $entry)) {
            return true;
        }

        return false;
    }

    protected function userCanCreate(Authorizable $user, Entry $entry): bool
    {
        if ($this->lockedDateService->get()->isAfter($entry->started_at)) {
            return false;
        }

        if ($user instanceof ApiToken) {
            return $user->can('entries.create_for_others');
        }

        assert($user instanceof User);

        if ($entry->budget !== null && ! $this->userCanCreateEntriesForBudget($user, $entry->budget)) {
            return false;
        }

        if (
            $user->can('entries.create_for_others')
            || $this->currentUserIsOwnerOf($user, $entry)
        ) {
            return true;
        }

        return false;
    }

    protected function userCanCreateEntriesForBudget(User $user, Budget $budget): bool
    {
        if ($budget->allowedUsers->isEmpty()) {
            return true;
        }

        if ($budget->allowedUsers->where('id', $user->id)->isNotEmpty()) {
            return true;
        }

        return false;
    }

    protected function currentUserIsOwnerOf(User $user, Entry $entry): bool
    {
        return (int) $entry->user_id === (int) $user->id;
    }

    protected function currentUserIsCreatorOf(User $user, Entry $entry): bool
    {
        return (int) $entry->created_by_user_id === (int) $user->id;
    }
}
