<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\SharedBudgetViewer;
use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
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

        $rateLimitKey = 'share-token:'.$viewer->budget->id;

        if (! RateLimiter::attempt($rateLimitKey, 60, fn () => true)) {
            throw new ThrottleRequestsException(headers: ['Retry-After' => RateLimiter::availableIn($rateLimitKey)]);
        }

        /** @var Route $route */
        $route = $request->route();
        $requestedBudgetId = $route->originalParameter('budget') ?? $request->input('filter.budgetId');
        $include = $request->input('include') ?? '';

        // Array query params (?include[]=x) are refused instead of cast, which would throw
        abort_unless(is_string($requestedBudgetId) && is_string($include), 403);

        $includes = array_filter(explode(',', $include));

        abort_unless(
            $request->isMethod('GET')
                && in_array($route->getName(), self::ALLOWED_ROUTES, true)
                && $requestedBudgetId === (string) $viewer->budget->id
                && array_diff($includes, self::ALLOWED_INCLUDES) === [],
            403,
        );

        return $next($request);
    }
}
