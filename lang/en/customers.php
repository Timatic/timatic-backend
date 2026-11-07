<?php

return [
    'email_domains' => [
        'label' => 'Email domains',
        'helper' => 'Email with an address on one of these domains is suggested for this customer. Only the exact domain matches: acme.com does not cover mail.acme.com. Separate multiple domains with a comma or space.',
        'helper_own' => 'Your company\'s own domains. They are ignored when matching email to a customer, because every mail contains one.',
        'invalid' => ':domain is not a valid domain.',
        'taken' => ':domain already belongs to :customer.',
    ],
    'own_organization' => [
        'helper' => 'Mark the customer that is your own company. Its email domains count as your own domains.',
    ],
];
