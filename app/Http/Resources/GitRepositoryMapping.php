<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * @mixin \App\DataTransferObjects\GitRepositoryMapping
 */
class GitRepositoryMapping extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'customerId' => (string) $this->customer_id,
            'budgetId' => $this->budget_id,
        ];
    }

    public function toType(Request $request): string
    {
        return 'git-repository-mappings';
    }

    public function toId(Request $request): string
    {
        return $this->repository;
    }
}
