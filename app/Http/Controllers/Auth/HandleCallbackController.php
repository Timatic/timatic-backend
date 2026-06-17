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

        if (filled($socialiteUser->token)) {
            $tokenData = [
                'oauth_access_token' => $socialiteUser->token,
                'oauth_token_expires_at' => now()->addSeconds(($socialiteUser->expiresIn ?? 3600) - 60)->timestamp,
            ];

            if (filled($socialiteUser->refreshToken)) {
                $tokenData['oauth_refresh_token'] = $socialiteUser->refreshToken;
            }

            $user->update($tokenData);
        }

        Auth::guard('web')->login($user);

        return Response::redirectTo(Session::pull('auth_original_url', '/'));
    }
}
