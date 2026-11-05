<?php

declare(strict_types=1);

namespace Timatic\Jira\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \Timatic\Jira\Models\ProjectMapping
 */
class ProjectMapping extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'projectKey' => $this->project_key,
            'projectName' => $this->project_name,
            'customerId' => $this->customer_id,
            'budgetId' => $this->budget_id,
            'isArchived' => $this->is_archived,
        ];
    }

    public function toType(Request $request): string
    {
        return 'jira-project-mappings';
    }
}
