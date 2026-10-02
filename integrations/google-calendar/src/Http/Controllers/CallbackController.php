<?php

namespace Timatic\GoogleCalendar\Http\Controllers;

use App\Models\User;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Timatic\GoogleCalendar\OAuthService;

class CallbackController
{
    public function __construct(private readonly OAuthService $oauth) {}

    /**
     * A user who declines at Google comes back carrying an error rather than a code. That is an
     * answer, not a failure, so they are returned to the frontend with their calendar simply left
     * unconnected.
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        if ($request->filled('code')) {
            $this->oauth->handleCallback(
                $user,
                $request->string('code')->toString(),
                $request->string('state')->toString(),
            );
        }

        return Redirect::away(rtrim((string) config('app.frontend_url'), '/').'/profile');
    }
}
