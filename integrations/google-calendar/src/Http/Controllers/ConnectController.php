<?php

namespace Timatic\GoogleCalendar\Http\Controllers;

use App\Models\User;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Timatic\GoogleCalendar\OAuthService;

class ConnectController
{
    public function __construct(private readonly OAuthService $oauth) {}

    #[ExcludeRouteFromDocs]
    public function __invoke(User $user): RedirectResponse
    {
        return Redirect::away($this->oauth->buildAuthorizationUrl($user));
    }
}
