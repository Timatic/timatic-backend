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
];
