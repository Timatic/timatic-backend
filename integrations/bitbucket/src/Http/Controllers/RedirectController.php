<?php

namespace Timatic\Bitbucket\Http\Controllers;

use App\Models\Integration;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Timatic\Bitbucket\OAuthService;

class RedirectController
{
    use AuthorizesRequests;

    public function __invoke(Integration $integration, OAuthService $oauth): RedirectResponse
    {
        $this->authorize('update', $integration);

        return redirect($oauth->buildAuthorizationUrl($integration));
    }
}
