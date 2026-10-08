<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\IssuedApiToken;
use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

class ApiTokenIssuer
{
    public function issue(ApiClient $client, User $user, string $deviceName): IssuedApiToken
    {
        $plainTextToken = Str::random(64);

        /** @var ApiToken $apiToken */
        $apiToken = ApiToken::query()->create([
            'user_id' => $user->id,
            'title' => $deviceName,
            'description' => $client->label,
            'key' => hash('sha512', $plainTextToken),
            'expires_at' => now()->addDays($client->token_lifetime_days),
        ]);

        return new IssuedApiToken($apiToken, $plainTextToken);
    }
}
