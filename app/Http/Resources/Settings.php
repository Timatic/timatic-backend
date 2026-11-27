<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * @mixin \App\DataTransferObjects\Settings
 */
class Settings extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'defaultHourlyRate' => $this->defaultHourlyRate,
        ];
    }

    public function toType(Request $request): string
    {
        return 'settings';
    }

    public function toId(Request $request): string
    {
        return 'settings';
    }
}
