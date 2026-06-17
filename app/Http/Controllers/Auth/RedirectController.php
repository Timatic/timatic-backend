<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\SocialiteRedirecting;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class RedirectController
{
    #[ExcludeRouteFromDocs]
    public function __invoke(): RedirectResponse
    {
        $event = new SocialiteRedirecting;

        event($event);

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver(config('auth.socialite_driver'));
        $driver->with(['access_type' => 'offline', 'prompt' => 'consent']);

        if ($event->getScopes() !== []) {
            $driver->scopes($event->getScopes());
        }

        return Response::redirectTo(
            $driver->redirect()->getTargetUrl()
        )->with(['auth_original_url' => URL::previous()]);
    }
}
