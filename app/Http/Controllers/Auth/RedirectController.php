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
    /**
     * Sends the browser straight to the identity provider. A deployment whose provider has no
     * credentials fails here with an answer of its own, rather than inside Socialite where the
     * error would read as the provider's.
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(): RedirectResponse
    {
        $driver = (string) config('auth.socialite_driver');

        abort_if(
            blank(config("services.{$driver}.client_id")),
            503,
            'This Timatic has no identity provider configured.',
        );

        $event = new SocialiteRedirecting;

        event($event);

        /** @var AbstractProvider $provider */
        $provider = Socialite::driver($driver);
        $provider->with(['access_type' => 'offline', 'prompt' => 'consent']);

        if ($event->getScopes() !== []) {
            $provider->scopes($event->getScopes());
        }

        return Response::redirectTo(
            $provider->redirect()->getTargetUrl()
        )->with(['auth_original_url' => URL::previous()]);
    }
}
