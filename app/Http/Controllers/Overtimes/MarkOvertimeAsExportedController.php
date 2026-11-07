<?php

declare(strict_types=1);

namespace App\Http\Controllers\Overtimes;

use App\Http\Resources;
use App\Models\Overtime;
use Carbon\Carbon;

class MarkOvertimeAsExportedController
{
    public function __invoke(Overtime $overtime): Resources\Overtime
    {
        $overtime->exported_at = Carbon::now();
        $overtime->save();

        return new Resources\Overtime($overtime);
    }
}
