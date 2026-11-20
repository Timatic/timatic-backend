<?php

return [
    'connect' => 'Connect',
    'install' => 'Install',

    'missing_config' => 'Set :keys in the server configuration before creating this integration.',

    'title_helper' => 'Optional, in case you need to install the same integration twice.',

    'chrome_extension' => [
        'label' => 'Chrome extension',
        'instructions' => "The extension records the time you spend on the domains you choose to track and shows it as suggestions.\n\nInstall the extension, enter the address of your Timatic installation and click *Koppel met Timatic* to connect it.",
    ],

    'unmapped_repositories_digest' => [
        'subject' => 'Work in :count repository without a customer|Work in :count repositories without a customer',
        'intro' => 'Work was recently done in these repositories, but they are not linked to a customer, so that time cannot be suggested on a customer. Click a repository to link it.',
        'outro' => 'Once linked, new work in the repository is booked on its customer. Internal work? Ignore the repository and it will not be mentioned again. Otherwise it is mentioned again next week if work continues.',
    ],
];
