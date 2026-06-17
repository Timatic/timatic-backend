<?php

namespace App\Http\Controllers;

use App\Http\Requests\EntrySuggestionCollectionRequest;
use App\Http\Resources\EntrySuggestion as EntrySuggestionResource;
use App\Models\EntrySuggestion;
use App\Models\User;
use App\QueryFilters\DateFilterCallback;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class EntrySuggestionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\EntrySuggestion', only: ['index']),
            new Middleware('can:view,entry_suggestion', only: ['show']),
            new Middleware('can:create,App\Models\EntrySuggestion', only: ['create', 'store']),
            new Middleware('can:update,entry_suggestion', only: ['edit', 'update']),
            new Middleware('can:delete,entry_suggestion', only: ['destroy']),
        ];
    }

    public function index(EntrySuggestionCollectionRequest $request): JsonApiResourceCollection
    {
        $query = EntrySuggestion::query();

        /** @var Authenticatable $user */
        $user = $request->user();

        if ($user instanceof User) {
            $query->where('user_id', $user->id);
        }

        $suggestions = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::callback('date', DateFilterCallback::make('date')),
            ])
            ->allowedIncludes([
                'activities.events.source',
            ])
            ->jsonPaginate();

        return EntrySuggestionResource::collection($suggestions);
    }

    public function show(EntrySuggestion $entrySuggestion): EntrySuggestionResource
    {
        $entrySuggestion->load('activities.events.source');

        return new EntrySuggestionResource($entrySuggestion);
    }

    /**
     * @throws Exception
     */
    public function destroy(EntrySuggestion $entrySuggestion): Response
    {
        $entrySuggestion->delete();

        return response()->noContent();
    }
}
