<?php

return [

    'paths' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('APP_FRONTEND_URL'),
        ...array_map(
            fn (string $extensionId): string => 'chrome-extension://'.$extensionId,
            config('extension.ids', []),
        ),
    ])),

    'supports_credentials' => true,

];
