<?php

declare(strict_types=1);
use App\Models\ApiToken;
use App\Models\User;

return [
    'guards' => [
        'api' => [
            'driver' => 'api_token',
            'provider' => 'api_tokens',
            'hash' => false,
        ],
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
            'hash' => false,
        ],
    ],
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
        'api_tokens' => [
            'driver' => 'eloquent',
            'model' => ApiToken::class,
        ],
    ],
    'socialite_driver' => env('SOCIALITE_DRIVER'),

    /*
     * What a user should read on the login button, since the driver key is not it. A deployment
     * runs exactly one provider, so adding one is a matter of a label and env credentials.
     */
    'socialite_labels' => [
        'azure' => 'Microsoft',
        'google' => 'Google',
        'auth0' => 'Auth0',
    ],
];
