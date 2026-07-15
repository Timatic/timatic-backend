<?php

namespace App\Enums;

enum ExportPeriodOptions: string
{
    case None = 'none';
    case Monthly = 'monthly';
    case MonthlyAndYearly = 'monthly-and-yearly';
}
