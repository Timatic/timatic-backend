<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\BudgetShareLink as BudgetShareLinkData;
use Illuminate\Http\Request;

/**
 * @mixin BudgetShareLinkData
 */
class BudgetShareLink extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            /** Shown once: only its hash is stored. */
            'token' => $this->token,
            /** The token sits in the fragment so it never reaches server logs. */
            'url' => rtrim((string) config('app.frontend_url'), '/')."/shared-budgets/{$this->budgetId}#{$this->token}",
        ];
    }

    public function toId(Request $request): string
    {
        return (string) $this->budgetId;
    }

    public function toType(Request $request): string
    {
        return 'budget-share-links';
    }
}
