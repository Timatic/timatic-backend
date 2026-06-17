<?php

declare(strict_types=1);

return [
    'tables' => [
        'users' => [
            'columns' => [
                'email' => 'faker:email',
                'given_name' => 'faker:firstName',
                'family_name' => 'faker:lastName',
            ],
        ],

        'events' => [
            'columns' => [
                'title' => 'faker:sentence',
                'description' => 'faker:paragraph',
            ],
        ],

        'activities' => [
            'columns' => [
                'title' => 'faker:sentence',
                'description' => 'faker:paragraph',
            ],
        ],

        'entries' => [
            'columns' => [
                'ticket_title' => 'faker:sentence',
                'customer_name' => 'faker:company',
                'user_full_name' => 'faker:name',
                'user_email' => 'faker:email',
                'created_by_user_full_name' => 'faker:name',
                'created_by_user_email' => 'faker:email',
                'description' => 'faker:paragraph',
            ],
        ],

        'customers' => [
            'columns' => [
                'name' => 'faker:company',
            ],
        ],

        'budget_versions' => [
            'columns' => [
                'title' => 'faker:sentence',
                'description' => 'faker:paragraph',
            ],
        ],

        'api_tokens' => [
            'method' => 'clear',
        ],

        'jobs' => [
            'method' => 'clear',
        ],

        'failed_jobs' => [
            'method' => 'clear',
        ],

        'sessions' => [
            'method' => 'clear',
        ],

    ],
];
