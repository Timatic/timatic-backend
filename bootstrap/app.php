<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RestrictShareTokenToSharedBudget;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/health',
        apiPrefix: ''
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: ['10.0.0.0/8'],
            headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withMiddleware(function (Middleware $middleware) {
        /*
         * The reauthenticate flag has to survive the trip to the login flow, since by the time the
         * authorization request is refused for want of a session it is the only thing left of it.
         */
        $middleware->redirectGuestsTo(fn (Request $request) => route(
            'auth.redirect',
            $request->boolean('reauthenticate') ? ['reauthenticate' => 1] : [],
        ));

        // A share token must not learn which ids exist, so it is refused before a 404 can happen
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: RestrictShareTokenToSharedBudget::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        /*
         * Api routes have no session to flash errors into and nowhere to redirect a browser back
         * to, so they answer every failure as json even when the caller sends no Accept header.
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson()
                || in_array('api', $request->route()?->gatherMiddleware() ?? [], true)
        );
    })->create();
