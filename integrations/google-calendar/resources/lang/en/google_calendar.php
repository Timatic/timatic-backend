<?php

return [
    'integration' => [
        'label' => 'Google Calendar',
        'instructions' => "The events in your Google Calendar show up as suggestions. Private events are skipped.\n\nAdd the ticket number to the title or description of an event to link the suggestion to that ticket.",
    ],

    'settings' => [
        'callout_not_configured_title' => 'Google Calendar credentials are not configured.',
        'callout_not_configured_description' => 'Add GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GOOGLE_CALENDAR_REDIRECT_URI to your .env file.',
        'field_title' => 'Integration title',
        'callout_connect_title' => 'Users connect their Google Calendar themselves.',
        'callout_connect_description' => 'A user grants calendar access manually, so only those who want their calendar read are asked. The table below shows all currently connected users.',
        'column_user' => 'User',
        'column_email' => 'Email',
        'column_connected_at' => 'Connected at',
        'action_disconnect' => 'Disconnect',
        'empty_heading' => 'No users connected yet',
        'empty_description' => 'Users will appear here after they connect their calendar from the integrations overview.',
        'action_save' => 'Save',
        'notification_title_updated' => 'Title updated.',
    ],
];
