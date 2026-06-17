<?php

return [

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
            'serve' => true,
            'report' => false,
        ],

        'temp' => [
            'driver' => 'local',
            'root' => env('EXCEL_TEMP_PATH') ?? storage_path('app/exports'),
            'throw' => false,
        ],
    ],

];
