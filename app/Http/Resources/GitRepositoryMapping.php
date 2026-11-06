<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\DataTransferObjects\GitRepositoryMapping
 */
class GitRepositoryMapping extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'customerId' => $this->customer_id,
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
