<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Token;

use App\Http\Requests\AuthorizeClientRequest;
use App\Models\User;
use App\Services\AuthorizationCodeService;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class ApproveAuthorizationController
{
    /**
     * Approving hands out a single use code. The form carries a csrf token; the signed url is
     * defence in depth, so the approved parameters cannot be swapped for another client's.
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(AuthorizeClientRequest $request, #[CurrentUser] User $user, AuthorizationCodeService $authorizationCodes): RedirectResponse
    {
        $code = $authorizationCodes->issueCode(
            $request->client(),
            $user,
            $request->codeChallenge(),
            $request->redirectUri(),
        );

        return redirect()->away($request->redirectUri().'?'.http_build_query([
            'code' => $code,
            'state' => $request->state(),
        ]));
    }
}
