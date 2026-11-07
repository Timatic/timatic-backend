<?php

return [
    'integration' => [
        'label' => 'Gmail',
        'instructions' => "De e-mails die je verstuurt en de concepten die je opslaat verschijnen als suggesties, gekoppeld aan de klant van wie het e-maildomein bij de ontvangers staat.\n\nAlleen de ontvangers, het onderwerp en het tijdstip van een mail worden gelezen, nooit de inhoud.",
    ],

    'settings' => [
        'field_title' => 'Titel van de integratie',
        'callout_connect_title' => 'Gebruikers koppelen hun Gmail zelf.',
        'callout_connect_description' => 'Een gebruiker geeft zelf toegang tot zijn mailbox, zodat alleen wie dat wil wordt gevraagd. De tabel hieronder toont alle gekoppelde gebruikers.',
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
