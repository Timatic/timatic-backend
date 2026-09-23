<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Token;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RevokeTokenController extends Controller
{
    #[ExcludeRouteFromDocs]
    public function __invoke(Request $request): Response
    {
        $key = $request->bearerToken() ?? $request->header('api-key');

        ApiToken::query()
            ->where('key', hash('sha512', (string) $key))
            ->delete();

        return response()->noContent();
    }
}
