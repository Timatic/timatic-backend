<?php

declare(strict_types=1);

namespace App\Http\Controllers\Extension;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtensionAuthorizeRequest;
use App\Models\User;
use App\Services\ExtensionAuthorizationService;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class AuthorizeController extends Controller
{
    public function __construct(private readonly ExtensionAuthorizationService $authorizations) {}

    /**
     * The consent screen. Reached through the regular web session, so an unauthenticated user is
     * sent through the existing Socialite login first and lands back here afterwards.
     */
    #[ExcludeRouteFromDocs]
    public function show(ExtensionAuthorizeRequest $request, #[CurrentUser] User $user): View
    {
        return view('extension.authorize', [
            'user' => $user,
            'approveUrl' => $this->approveUrl($request),
            'denyUrl' => $request->redirectUri().'?'.http_build_query([
                'error' => 'access_denied',
                'state' => $request->state(),
            ]),
        ]);
    }

    /**
     * Approving hands out a single use code. The form posts to a signed url on top of the csrf
     * token: without the signature, the approved parameters could be swapped for another
     * extension's redirect uri after the consent screen was rendered.
     */
    #[ExcludeRouteFromDocs]
    public function approve(ExtensionAuthorizeRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $code = $this->authorizations->issueCode($user, $request->codeChallenge(), $request->redirectUri());

        return redirect()->away($request->redirectUri().'?'.http_build_query([
            'code' => $code,
            'state' => $request->state(),
        ]));
    }

    private function approveUrl(ExtensionAuthorizeRequest $request): string
    {
        return URL::temporarySignedRoute(
            'extension.authorize.approve',
            now()->addMinutes((int) config('extension.consent_lifetime_minutes')),
            $request->validated(),
        );
    }
}
