<?php

return [

    'paths' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('APP_FRONTEND_URL'),
        ...array_map(
            fn (string $extensionId): string => 'chrome-extension://'.$extensionId,
            array_filter(array_map('trim', explode(',', (string) env('EXTENSION_IDS', '')))),
        ),
    ])),

    'supports_credentials' => true,

];
