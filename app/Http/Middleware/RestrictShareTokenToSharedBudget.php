<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\SharedBudgetViewer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A share token only reads what the shared budget page needs: the budget, its periods and its time spent totals.
 */
class RestrictShareTokenToSharedBudget
{
    private const array ALLOWED_ROUTES = ['budgets.show', 'budget.periods.show', 'budget.time-spent-totals'];

    private const array ALLOWED_INCLUDES = ['customer', 'budgetType'];

    public function handle(Request $request, Closure $next): mixed
    {
        $viewer = $request->user();

        if (! $viewer instanceof SharedBudgetViewer) {
            return $next($request);
        }

        abort_unless(RateLimiter::attempt('share-token:'.$viewer->budget->id, 60, fn () => true), 429);

        /** @var Route $route */
        $route = $request->route();
        $requestedBudgetId = $route->originalParameter('budget') ?? $request->input('filter.budgetId');
        $includes = array_filter(explode(',', $request->string('include')->toString()));

        abort_unless(
            $request->isMethod('GET')
                && in_array($route->getName(), self::ALLOWED_ROUTES, true)
                && (string) $requestedBudgetId === (string) $viewer->budget->id
                && array_diff($includes, self::ALLOWED_INCLUDES) === [],
            403,
        );

        return $next($request);
    }
}
