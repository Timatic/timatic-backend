<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budgets;

use App\DataTransferObjects\BudgetShareLink as BudgetShareLinkData;
use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetShareLink as BudgetShareLinkResource;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class BudgetShareLinkController extends Controller
{
    /**
     * Create a share link for the budget, replacing any earlier link.
     *
     * The customer opens the link without logging in and sees the budget's hours, never its entries
     * or prices. The token is only returned here; generate a new link if it is lost.
     */
    public function store(Budget $budget): JsonResponse
    {
        if (! $budget->show_to_customer) {
            abort(422, __('budgets.share_link.hidden_from_customer'));
        }

        $token = Str::random(48);

        $budget->share_token_hash = hash('sha256', $token);
        $budget->save();

        return (new BudgetShareLinkResource(new BudgetShareLinkData($budget->id, $token)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Revoke the budget's share link.
     */
    public function destroy(Budget $budget): Response
    {
        $budget->share_token_hash = null;
        $budget->save();

        return response()->noContent();
    }
}
