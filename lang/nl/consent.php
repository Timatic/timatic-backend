<?php

return [
    'title' => 'Integratie koppelen',

    'authorize' => [
        'subtitle' => ':client koppelen',
        'intro' => ':client wil toegang tot Timatic als :name (:email).',
        'scopes' => [
            'Tijd registreren op domeinen die jij hebt aangezet',
            'Klanten en budgetten lezen om een domein te koppelen',
        ],
        'revoke_hint' => 'Je kunt de koppeling later intrekken vanuit de applicatie of in Timatic.',
        'approve' => 'Koppelen',
        'deny' => 'Annuleren',
    ],
];
