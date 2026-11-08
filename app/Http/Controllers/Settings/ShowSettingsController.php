<?php

namespace App\Http\Controllers\Settings;

use App\DataTransferObjects\Settings;
use App\Http\Controllers\Controller;
use App\Http\Resources\Settings as SettingsResource;

class ShowSettingsController extends Controller
{
    public function __invoke(): SettingsResource
    {
        $defaultHourlyRate = config('timatic.default_hourly_rate');

        return new SettingsResource(new Settings(
            defaultHourlyRate: is_numeric($defaultHourlyRate) ? (float) $defaultHourlyRate : null,
        ));
    }
}
