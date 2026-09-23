<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;

class ShowProviderController extends Controller
{
    /**
     * Names the single identity provider this deployment runs, so the login screen can render one
     * button rather than a picker. A deployment whose provider has no credentials fails here
     * instead of at the Socialite redirect, where the error would be the provider's to explain.
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(): JsonResponse
    {
        $driver = (string) config('auth.socialite_driver');

        abort_if(
            blank(config("services.{$driver}.client_id")),
            503,
            'This Timatic has no identity provider configured.',
        );

        return response()->json([
            'driver' => $driver,
            'label' => (string) config("auth.socialite_labels.{$driver}", $driver),
        ]);
    }
}
