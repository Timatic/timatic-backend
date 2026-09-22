<?php

declare(strict_types=1);

use App\Support\ExtensionIds;

return [
    /*
     * Chrome extension ids that are allowed to start an authorisation flow. Each id maps to
     * exactly one redirect uri: https://{id}.chromiumapp.org/
     */
    'ids' => ExtensionIds::parse((string) env('EXTENSION_IDS', '')),

    'token_lifetime_days' => (int) env('EXTENSION_TOKEN_LIFETIME_DAYS', 90),

    'code_lifetime_seconds' => 60,

    'consent_lifetime_minutes' => 5,
];
