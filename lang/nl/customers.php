<?php

return [
    'email_domains' => [
        'label' => 'E-maildomeinen',
        'helper' => 'E-mail met een adres op een van deze domeinen wordt voor deze klant voorgesteld. Alleen het exacte domein telt: acme.com dekt mail.acme.com niet. Scheid meerdere domeinen met een komma of spatie.',
        'helper_own' => 'De domeinen van je eigen bedrijf. Die worden genegeerd bij het koppelen van e-mail aan een klant, omdat ze in elke mail voorkomen.',
        'invalid' => ':domain is geen geldig domein.',
        'taken' => ':domain hoort al bij :customer.',
    ],
    'own_organization' => [
        'helper' => 'Markeer de klant die je eigen bedrijf is. De e-maildomeinen daarvan gelden als je eigen domeinen.',
    ],
];
