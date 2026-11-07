<?php

return [
    'email_domains' => [
        'label' => 'Email domains',
        'helper' => 'Email to an address on one of these domains is booked on this customer, e.g. acme.com. Only the exact domain matches, not its subdomains. On your own organization, list your company domains: they never resolve a customer.',
        'invalid' => ':domain is not a valid domain.',
        'taken' => ':domain already belongs to another customer.',
    ],
];
