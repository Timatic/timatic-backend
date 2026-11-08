<?php

return [
    'integration' => [
        'label' => 'Gmail',
        'instructions' => "De e-mails die je verstuurt en de concepten die je opslaat verschijnen als suggesties, gekoppeld aan de klant van wie het e-maildomein bij de ontvangers staat.\n\nAlleen de ontvangers, het onderwerp en het tijdstip van een mail worden gelezen, nooit de inhoud.",
    ],

    'settings' => [
        'field_title' => 'Titel van de integratie',
        'callout_connect_title' => 'Gebruikers koppelen hun Gmail zelf.',
        'callout_connect_description' => 'Gebruikers geven zelf toegang tot hun mailbox, dus alleen wie dat wil wordt gevraagd. Timatic leest alleen de ontvangers, het onderwerp en het tijdstip van verstuurde mails en opgeslagen concepten, nooit de inhoud. Een mail wordt aan een klant gekoppeld als het adres van een ontvanger op een van de e-maildomeinen van die klant staat, dus vul die in bij de klanten. De tabel hieronder toont alle gekoppelde gebruikers.',
        'column_user' => 'Gebruiker',
        'column_email' => 'E-mail',
        'column_connected_at' => 'Gekoppeld op',
        'action_disconnect' => 'Ontkoppelen',
        'empty_heading' => 'Nog geen gebruikers gekoppeld',
        'empty_description' => 'Gebruikers verschijnen hier zodra ze hun Gmail koppelen vanuit het overzicht van integraties.',
        'action_save' => 'Opslaan',
        'notification_title_updated' => 'Titel bijgewerkt.',
    ],
];
