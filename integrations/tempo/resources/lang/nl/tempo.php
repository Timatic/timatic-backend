<?php

return [
    'settings' => [
        'page_title' => ':title (Tempo) — Instellingen',
        'field_title' => 'Titel',
        'callout_no_jira_title' => 'Geen Jira-integratie',
        'callout_no_jira_description' => 'Tempo boekt tijd op Jira-issues. Verbind eerst een Jira-integratie en koppel die hier.',
        'field_api_token' => 'Tempo API-token',
        'field_api_token_helper' => 'Maak een token aan in Tempo onder Settings > API integration met lees- en schrijfrechten op worklogs.',
        'field_api_token_helper_stored' => 'Er is een token opgeslagen. Laat leeg om het te behouden of plak een nieuw token om het te vervangen.',
        'field_jira_integration' => 'Jira-instantie',
        'field_jira_integration_helper' => 'De Jira-instantie waarop Tempo is geïnstalleerd. De projectkoppeling daarvan bepaalt de klant en het budget van gesynchroniseerde entries.',
        'field_sync_to_timatic' => 'Tempo-worklogs synchroniseren naar Timatic-entries',
        'field_sync_to_timatic_helper' => 'Elke vijftien minuten worden de in Tempo gewijzigde worklogs naar entries gekopieerd, met de echte auteur. Vervangt de worklog-synchronisatie van de gekoppelde Jira-instantie.',
        'field_sync_to_tempo' => 'Timatic-entries synchroniseren naar Tempo',
        'field_sync_to_tempo_helper' => 'Entries op issues van een gekoppeld Jira-project worden in Tempo aangemaakt, bijgewerkt en verwijderd als worklogs van de gebruiker van de entry.',
        'action_save' => 'Opslaan',
        'notification_saved' => 'Instellingen opgeslagen.',
    ],
];
