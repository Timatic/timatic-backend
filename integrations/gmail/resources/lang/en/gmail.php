<?php

return [
    'integration' => [
        'label' => 'Gmail',
        'instructions' => "The emails you send and the drafts you save show up as suggestions, linked to the customer whose email domain is among the recipients.\n\nOnly the recipients, subject and time of a mail are read, never its content.\n\nOnly mail from after you connect is picked up.",
    ],

    'settings' => [
        'field_title' => 'Integration title',
        'callout_connect_title' => 'Users connect their Gmail themselves.',
        'callout_connect_description' => 'A user grants mailbox access manually, so only those who want their mail read are asked. Timatic only reads the recipients, subject and time of sent mails and saved drafts, never their content. A mail is linked to a customer when a recipient\'s address is on one of that customer\'s email domains, so set those on the customers. The table below shows all currently connected users.',
        'column_user' => 'User',
        'column_email' => 'Email',
        'column_connected_at' => 'Connected at',
        'action_disconnect' => 'Disconnect',
        'empty_heading' => 'No users connected yet',
        'empty_description' => 'Users will appear here after they connect their Gmail from the integrations overview.',
        'action_save' => 'Save',
        'notification_title_updated' => 'Title updated.',
    ],
];
