<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\Overtime
 */
class Overtime extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'entryId' => $this->entry_id,
            'overtimeTypeId' => $this->overtime_type_id,
            'startedAt' => $this->started_at,
            'endedAt' => $this->ended_at,
            'percentages' => $this->percentages,
            'approvedAt' => $this->approved_at,
            'approvedByUserId' => $this->approved_by_user_id,
            'exportedAt' => $this->exported_at,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'overtimeType' => OvertimeType::class,
        'entry' => Entry::class,
    ];
}
