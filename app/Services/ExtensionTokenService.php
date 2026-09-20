<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\IssuedExtensionToken;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

class ExtensionTokenService
{
    public function issue(User $user, string $deviceName): IssuedExtensionToken
    {
        $plainTextToken = Str::random(64);

        /** @var ApiToken $apiToken */
        $apiToken = ApiToken::query()->create([
            'user_id' => $user->id,
            'title' => $deviceName,
            'description' => 'Browser extension',
            'key' => hash('sha512', $plainTextToken),
            'expires_at' => now()->addDays((int) config('extension.token_lifetime_days')),
        ]);

        return new IssuedExtensionToken($apiToken, $plainTextToken);
    }
}
