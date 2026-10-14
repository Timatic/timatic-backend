<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ApiClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ApiClientSeeder extends Seeder
{
    /**
     * The first party clients every deployment runs. Their redirect uris come from the environment,
     * so re-run this after changing APP_FRONTEND_URL or EXTENSION_IDS.
     */
    public function run(): void
    {
        /** @var list<string> $allowedOrigins */
        $allowedOrigins = config('cors.allowed_origins');

        ApiClient::updateOrCreate(['id' => 'chrome-extension'], [
            'label' => 'Browser extension',
            'redirect_uris' => collect($allowedOrigins)
                ->filter(fn (string $origin): bool => str_starts_with($origin, 'chrome-extension://'))
                ->map(fn (string $origin): string => 'https://'.Str::after($origin, 'chrome-extension://').'.chromiumapp.org/')
                ->values()
                ->all(),
            'token_lifetime_days' => 90,
            'auto_approve' => false,
        ]);

        ApiClient::updateOrCreate(['id' => 'web'], [
            'label' => 'Timatic web app',
            'redirect_uris' => [rtrim((string) config('app.frontend_url'), '/').'/auth/callback'],
            'token_lifetime_days' => 30,
            // Asking a user to consent to Timatic on behalf of Timatic is noise.
            'auto_approve' => true,
        ]);
    }
}
