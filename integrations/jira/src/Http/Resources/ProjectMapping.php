<?php

declare(strict_types=1);

namespace Timatic\Jira\Http\Resources;

use App\Http\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * @mixin \Timatic\Jira\Models\ProjectMapping
 */
class ProjectMapping extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'projectKey' => $this->project_key,
            'projectName' => $this->project_name,
            'customerId' => $this->customer_id === null ? null : (string) $this->customer_id,
            'budgetId' => $this->budget_id,
        ];
    }

    public function toType(Request $request): string
    {
        return 'jira-project-mappings';
    }
}
