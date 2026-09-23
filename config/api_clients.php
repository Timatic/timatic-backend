<?php

declare(strict_types=1);

use App\Support\ExtensionIds;

return [
    'code_lifetime_seconds' => 60,

    'consent_lifetime_minutes' => 5,

    /*
     * Clients that may run the authorization code flow. Each one owns the redirect uris it is
     * allowed to send a code to, so a code minted for one client can never be delivered to
     * another.
     */
    'clients' => [
        'extension' => [
            'label' => 'Browser extension',
            'redirect_uris' => ExtensionIds::redirectUris((string) env('EXTENSION_IDS', '')),
            'token_lifetime_days' => (int) env('EXTENSION_TOKEN_LIFETIME_DAYS', 90),
        ],

        'web' => [
            'label' => 'Timatic web app',
            'redirect_uris' => [rtrim((string) env('APP_FRONTEND_URL', ''), '/').'/auth/callback'],
            'token_lifetime_days' => (int) env('WEB_TOKEN_LIFETIME_DAYS', 30),
        ],
    ],
];
