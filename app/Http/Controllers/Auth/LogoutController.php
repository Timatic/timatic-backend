<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class LogoutController
{
    /**
     * Ends the browser session the authorization code flow runs on. Throwing away the api token is
     * not enough on its own: the next authorization request would find a live session here, approve
     * itself and hand out a new token without the user ever seeing a login screen.
     *
     * Reached as a plain navigation, which is what lets the session cookie travel at all, so a
     * cross site request can trigger it. Forcing a logout is the whole of what that buys, and it
     * costs the user one trip past the identity provider.
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Response::redirectTo(
            rtrim((string) config('app.frontend_url'), '/').'/login'
        );
    }
}
