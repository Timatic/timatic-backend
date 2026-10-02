<?php

namespace Timatic\GoogleCalendar\Http\Controllers;

use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Timatic\GoogleCalendar\OAuthService;

class ConnectController
{
    public function __construct(private readonly OAuthService $oauth) {}

    #[ExcludeRouteFromDocs]
    public function __invoke(): RedirectResponse
    {
        return Redirect::away($this->oauth->buildAuthorizationUrl());
    }
}
