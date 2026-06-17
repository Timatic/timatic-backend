<?php

return [
    'release' => env('APP_VERSION'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#ignore-transactions
    'ignore_transactions' => [
        // Ignore the health URL
        '/health',
    ],
];
