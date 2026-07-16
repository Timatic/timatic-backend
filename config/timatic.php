<?php

return [
    'tenant_slug' => env('TENANT_SLUG'),
    'tenant_name' => env('TENANT_NAME'),
    'tenant_external_customer_id' => env('TENANT_EXTERNAL_CUSTOMER_ID'),
    'working_hours' => [
        'start' => '08:00',
        'end' => '18:00',
        'days' => [1, 2, 3, 4, 5],
    ],
    'entries_locked_after_days' => 10,
    'month-end_closing_day_of_month' => 6,
    'extended_closing_day_of_month' => 15,
    'feature' => [
        'align_periods_to_month_start' => env('ALIGN_PERIODS_TO_MONTH_START', true),
    ],
    'account_management_mail_address' => env('ACCOUNT_MANAGEMENT_MAIL_ADDRESS'),
    'preferred_timezone' => 'Europe/Amsterdam',
    'default_hourly_rate' => env('DEFAULT_HOURLY_RATE'),
];
