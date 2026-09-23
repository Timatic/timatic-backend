<?php

return [
    'title' => 'Connect integration',

    'authorize' => [
        'subtitle' => 'Connect :client',
        'intro' => ':client wants access to Timatic as :name (:email).',
        'scopes' => [
            'Log time on domains you have switched on',
            'Read customers and budgets to connect a domain',
        ],
        'revoke_hint' => 'You can revoke this connection later from the application or in Timatic.',
        'approve' => 'Connect',
        'deny' => 'Cancel',
    ],
];
