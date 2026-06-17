<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\DerivedPermission;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\EntryLockedDateService;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class ShowCurrentUserController extends Controller
{
    /**
     * @param  User|ApiToken  $user
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(
        #[CurrentUser] $user,
        Request $request,
        EntryLockedDateService $lockedDateService
    ): \App\Http\Resources\User {

        if ($user instanceof User) {
            $user->loadMissing('team');
        }

        $user->loadMissing(['permissions', 'roles.permissions']);

        $user->derivedPermissions[] = new DerivedPermission(
            name: 'entries.update_in_past_until',
            values: ['lockedAt' => $lockedDateService->get()]
        );

        return new \App\Http\Resources\User($user);
    }
}
