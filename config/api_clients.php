<?php

declare(strict_types=1);

use App\Support\ExtensionIds;

return [
    'code_lifetime_seconds' => 60,

    'consent_lifetime_minutes' => 5,

    /*
     * Defaults for the two first party clients, seeded into the api_clients table. The table is
     * what the authorization flow reads; re-run ApiClientSeeder after changing these.
     */
    'clients' => [
        'extension' => [
            'label' => 'Browser extension',
            'redirect_uris' => ExtensionIds::redirectUris((string) env('EXTENSION_IDS', '')),
            'token_lifetime_days' => (int) env('EXTENSION_TOKEN_LIFETIME_DAYS', 90),
            'auto_approve' => false,
        ],

        'web' => [
            'label' => 'Timatic web app',
            'redirect_uris' => [rtrim((string) env('APP_FRONTEND_URL', ''), '/').'/auth/callback'],
            'token_lifetime_days' => (int) env('WEB_TOKEN_LIFETIME_DAYS', 30),

            /*
             * Timatic's own frontend. Asking a user to consent to Timatic on behalf of Timatic is
             * noise, so it skips the consent screen.
             */
            'auto_approve' => true,
        ],
    ],
];
