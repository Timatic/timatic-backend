<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class LogoutController
{
    /**
     * Ends the browser session the authorization code flow runs on. Throwing away the api token is
     * not enough on its own: the next authorization request would find a live session here, approve
     * itself and hand out a new token without the user ever seeing a login screen.
     *
     * The identity provider's own session is left alone. It is shared with everything else that
     * signs in through it, so ending it is not Timatic's call to make. The frontend asks for the
     * provider to be passed again on the next authorization request instead.
     *
     * Reached as a plain navigation, which is what lets the session cookie travel at all, so a
     * cross site request can trigger it. Forcing a logout is the whole of what that buys.
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::away(
            rtrim((string) config('app.frontend_url'), '/').'/login'
        );
    }
}
