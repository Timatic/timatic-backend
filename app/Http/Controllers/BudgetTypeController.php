<?php

namespace App\Http\Controllers;

use App\Http\Resources\BudgetType as BudgetTypeResource;
use App\Models\BudgetType;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class BudgetTypeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\BudgetType', only: ['index']),
            new Middleware('can:view,budget_type', only: ['show']),
            new Middleware('can:create,App\Models\BudgetType', only: ['create', 'store']),
            new Middleware('can:update,budget_type', only: ['edit', 'update']),
            new Middleware('can:delete,budget_type', only: ['destroy']),
        ];
    }

    public function index(): JsonApiResourceCollection
    {
        return BudgetTypeResource::collection(BudgetType::all());
    }
}
