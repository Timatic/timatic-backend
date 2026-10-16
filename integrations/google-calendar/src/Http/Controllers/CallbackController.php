<?php

declare(strict_types=1);

namespace Timatic\GoogleCalendar\Http\Controllers;

use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Timatic\GoogleCalendar\Exceptions\CalendarConnectionFailedException;
use Timatic\GoogleCalendar\OAuthService;

class CallbackController
{
    public function __construct(private readonly OAuthService $oauth) {}

    /**
     * A user who declines at Google comes back carrying an error rather than a code. That is an
     * answer, not a failure, so they are returned to the frontend with their calendar simply left
     * unconnected. A failed connect lands there too: a stale or replayed callback is answered by
     * connecting again, not by an error page.
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->filled('code')) {
            try {
                $this->oauth->handleCallback(
                    $request->string('code')->toString(),
                    $request->string('state')->toString(),
                );
            } catch (CalendarConnectionFailedException $exception) {
                report($exception);
            }
        }

        return Redirect::away(rtrim((string) config('app.frontend_url'), '/').'/');
    }
}
