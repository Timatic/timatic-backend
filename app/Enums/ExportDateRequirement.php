<?php

namespace App\Enums;

enum ExportDateRequirement: string
{
    case None = 'none';
    case Monthly = 'monthly';
    case MonthlyAndYearly = 'monthly-and-yearly';
}
