<?php

namespace Timatic\Jira\Http\Controllers;

use App\Models\Integration;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Timatic\Jira\OAuthService;

class RedirectController
{
    use AuthorizesRequests;

    public function __invoke(Integration $integration, OAuthService $oauth): RedirectResponse
    {
        $this->authorize('update', $integration);

        return redirect($oauth->buildAuthorizationUrl($integration));
    }
}
