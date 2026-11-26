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

        $user = User::firstOrNew(['email' => $socialiteUser->getEmail()]);
        $user->external_id ??= $socialiteUser->getId();
        $user->given_name ??= $socialiteUser->getRaw()['given_name'] ?? null;
        $user->family_name ??= $socialiteUser->getRaw()['family_name'] ?? $socialiteUser->getName();
        $user->save();

        // Users added by hand (e.g. for a Tempo sync) have no role until their first login
        if ($user->roles()->doesntExist()) {
            $role = User::count() === 1 ? 'super_admin' : 'employee';
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            $user->assignRole($role);
        }

        Auth::guard('web')->login($user);

        $intended = Session::pull('url.intended');
        $previous = Session::pull('auth_original_url', '/');

        return Response::redirectTo($intended ?? $previous);
    }
}
