<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Token;

use App\DataTransferObjects\ApiClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuthorizeClientRequest;
use App\Models\User;
use App\Services\AuthorizationCodeService;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class AuthorizeController extends Controller
{
    public function __construct(private readonly AuthorizationCodeService $authorizationCodes) {}

    /**
     * The consent screen. Reached through the regular web session, so an unauthenticated user is
     * sent through the existing Socialite login first and lands back here afterwards. A first party
     * client skips the screen: asking a user to consent to Timatic on behalf of Timatic is noise,
     * and the code still only travels to that client's registered redirect uri.
     */
    #[ExcludeRouteFromDocs]
    public function show(AuthorizeClientRequest $request, #[CurrentUser] User $user): View|RedirectResponse
    {
        $client = $request->client();

        if ($client->autoApprove) {
            return $this->redirectWithCode($client, $request, $user);
        }

        return view('oauth.authorize', [
            'user' => $user,
            'client' => $client,
            'approveUrl' => $this->approveUrl($request),
            'denyUrl' => $request->redirectUri().'?'.http_build_query([
                'error' => 'access_denied',
                'state' => $request->state(),
            ]),
        ]);
    }

    /**
     * Approving hands out a single use code. The form carries a csrf token; the signed url is
     * defence in depth, so the approved parameters cannot be swapped for another client's.
     */
    #[ExcludeRouteFromDocs]
    public function approve(AuthorizeClientRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        return $this->redirectWithCode($request->client(), $request, $user);
    }

    private function redirectWithCode(ApiClient $client, AuthorizeClientRequest $request, User $user): RedirectResponse
    {
        $code = $this->authorizationCodes->issueCode(
            $client,
            $user,
            $request->codeChallenge(),
            $request->redirectUri(),
        );

        return redirect()->away($request->redirectUri().'?'.http_build_query([
            'code' => $code,
            'state' => $request->state(),
        ]));
    }

    private function approveUrl(AuthorizeClientRequest $request): string
    {
        return URL::temporarySignedRoute(
            'oauth.authorize.approve',
            now()->addMinutes((int) config('api_clients.consent_lifetime_minutes')),
            $request->validated(),
        );
    }
}
