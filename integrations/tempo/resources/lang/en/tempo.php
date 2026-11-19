<?php

return [
    'settings' => [
        'page_title' => ':title (Tempo) — Settings',
        'field_title' => 'Title',
        'callout_no_jira_title' => 'No Jira integration',
        'callout_no_jira_description' => 'Tempo logs time on Jira issues. Connect a Jira integration first, then link it here.',
        'field_api_token' => 'Tempo API token',
        'field_api_token_helper' => 'Create a token in Tempo under Settings > API integration with worklog read and write permissions.',
        'field_api_token_helper_stored' => 'A token is stored. Leave empty to keep it, or paste a new one to replace it.',
        'field_jira_integration' => 'Jira instance',
        'field_jira_integration_helper' => 'The Jira instance Tempo is installed on. Its project mapping decides the customer and budget of synced entries.',
        'field_sync_to_timatic' => 'Sync Tempo worklogs to Timatic entries',
        'field_sync_to_timatic_helper' => 'Every fifteen minutes the worklogs changed in Tempo are copied to entries, with the real author. Replaces the worklog sync of the linked Jira instance.',
        'field_sync_to_tempo' => 'Sync Timatic entries to Tempo',
        'field_sync_to_tempo_helper' => 'Entries on issues of a mapped Jira project are created, updated and deleted in Tempo as worklogs of the entry\'s user.',
        'action_save' => 'Save',
        'notification_saved' => 'Settings saved.',
    ],
];
