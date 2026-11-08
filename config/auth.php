<?php

declare(strict_types=1);
use App\Models\ApiToken;
use App\Models\User;
use App\Support\Auth\SharedBudgetViewer;

return [
    'guards' => [
        'api' => [
            'driver' => 'api_token',
            'provider' => 'api_tokens',
            'hash' => false,
        ],
        'share_token' => [
            'driver' => 'share_token',
            'provider' => 'shared_budget_viewers',
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
        // Never queried: the share_token guard resolves its viewer itself; this tells Larastan its type
        'shared_budget_viewers' => [
            'driver' => 'eloquent',
            'model' => SharedBudgetViewer::class,
        ],
    ],
    'socialite_driver' => env('SOCIALITE_DRIVER'),
];
