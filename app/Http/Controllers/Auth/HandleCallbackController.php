<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class HandleCallbackController
{
    #[ExcludeRouteFromDocs]
    public function __invoke(): RedirectResponse
    {
        // @phpstan-ignore-next-line
        $socialiteUser = Socialite::driver(config('auth.socialite_driver'))->stateless()->user();

        $user = User::firstOrCreate(
            [
                'email' => $socialiteUser->getEmail(),
            ],
            [
                'external_id' => $socialiteUser->getId(),
                'family_name' => $socialiteUser->getName(),
            ]
        );

        if ($user->wasRecentlyCreated) {
            $role = User::count() === 1 ? 'super_admin' : 'employee';
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            $user->assignRole($role);
        }

        /*
         * The provider's own tokens are not kept. Signing in proves who someone is and nothing
         * else; the calendar integration asks for its own access when a user connects, and storing
         * an identity token here would leave it looking like a calendar grant that cannot read a
         * calendar.
         */
        Auth::guard('web')->login($user);

        $intended = Session::pull('url.intended');
        $previous = Session::pull('auth_original_url', '/');

        return Response::redirectTo($intended ?? $previous);
    }
}
