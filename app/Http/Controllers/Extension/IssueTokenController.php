<?php

declare(strict_types=1);

namespace App\Http\Controllers\Extension;

use App\Exceptions\InvalidAuthorizationCodeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExtensionTokenRequest;
use App\Services\ExtensionAuthorizationService;
use App\Services\ExtensionTokenService;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;

class IssueTokenController extends Controller
{
    public function __construct(
        private readonly ExtensionAuthorizationService $authorizations,
        private readonly ExtensionTokenService $tokens,
    ) {}

    /**
     * Exchanges a single use code for a bearer token. The token is returned in the response body
     * only, so it never ends up in a url, in browser history or in a referer header.
     *
     * @throws InvalidAuthorizationCodeException
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(ExtensionTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->authorizations->claimCode(
            $validated['code'],
            $validated['code_verifier'],
            $validated['redirect_uri'],
        );

        $issuedToken = $this->tokens->issue($user, $validated['device_name']);

        return response()->json([
            'token' => $issuedToken->plainTextToken,
            'expiresAt' => $issuedToken->apiToken->expires_at?->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'fullName' => $user->full_name,
                'email' => $user->email,
            ],
        ], 201);
    }
}
