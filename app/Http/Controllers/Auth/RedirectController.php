<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function __invoke(Request $request): RedirectResponse
    {
        $driver = (string) config('auth.socialite_driver');

        abort_if(
            blank(config("services.{$driver}.client_id")),
            503,
            'This Timatic has no identity provider configured.',
        );

        $parameters = [];

        /*
         * A caller that asks for it has the user pass the provider again rather than being carried
         * through on the session it still holds, which is what the frontend asks for after someone
         * signs out. Reauthentication rather than an account picker: Auth0 renders a picker only
         * for upstream connections and silently signs the user back in otherwise.
         */
        if ($request->boolean('reauthenticate')) {
            $parameters['prompt'] = 'login';
        }

        /** @var AbstractProvider $provider */
        $provider = Socialite::driver($driver);
        $provider->with($parameters);

        return Response::redirectTo(
            $provider->redirect()->getTargetUrl()
        )->with(['auth_original_url' => URL::previous()]);
    }
}
