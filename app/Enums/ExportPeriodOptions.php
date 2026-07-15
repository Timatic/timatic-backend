<?php

namespace App\Enums;

enum ExportPeriodOptions: string
{
    case None = 'none';
    case Monthly = 'monthly';
    case MonthlyAndYearly = 'monthly-and-yearly';

    public function yearIsRequired(): bool
    {
        return $this == self::Monthly || $this == self::MonthlyAndYearly;
    }

    public function monthIsRequired(): bool
    {
        return $this === self::Monthly;
    }
}
