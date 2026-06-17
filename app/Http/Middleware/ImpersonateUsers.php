<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonateUsers
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var User|ApiToken $user */
        $user = Auth::user();

        if ($user instanceof ApiToken) {
            return $next($request);
        }

        if ($user->can('users.impersonate') && $request->hasHeader('impersonate-user-id')) {
            /** @var User $impersonatedUser */
            $impersonatedUser = User::query()->find($request->header('impersonate-user-id'));
            $impersonatedUser->impersonate();
            Auth::setUser($impersonatedUser);
        }

        return $next($request);
    }
}
