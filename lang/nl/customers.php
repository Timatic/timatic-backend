<?php

return [
    'email_domains' => [
        'label' => 'E-maildomeinen',
        'helper' => 'E-mail aan een adres op een van deze domeinen wordt op deze klant geboekt, bijv. acme.com. Alleen het exacte domein telt, niet de subdomeinen. Vul bij je eigen organisatie je bedrijfsdomeinen in: die leveren nooit een klant op.',
        'invalid' => ':domain is geen geldig domein.',
        'taken' => ':domain hoort al bij een andere klant.',
    ],
];
