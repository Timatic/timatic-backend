<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Token;

use App\Exceptions\InvalidAuthorizationCodeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\IssueTokenRequest;
use App\Services\ApiTokenIssuer;
use App\Services\AuthorizationCodeService;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;

class IssueTokenController extends Controller
{
    public function __construct(
        private readonly AuthorizationCodeService $authorizationCodes,
        private readonly ApiTokenIssuer $tokens,
    ) {}

    /**
     * Exchanges a single use code for a bearer token. The token is returned in the response body
     * only, so it never ends up in a url, in browser history or in a referer header.
     *
     * @throws InvalidAuthorizationCodeException
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(IssueTokenRequest $request): JsonResponse
    {
        $client = $request->client();

        $user = $this->authorizationCodes->claimCode(
            $client,
            $request->code(),
            $request->codeVerifier(),
            $request->redirectUri(),
        );

        $issuedToken = $this->tokens->issue($client, $user, $request->deviceName());

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
