<?php

namespace App\Http\Controllers;

use App\Http\Requests\EntryCollectionRequest;
use App\Http\Requests\EntryCreateRequest;
use App\Http\Requests\EntryUpdateRequest;
use App\Http\Resources\Entry as EntryResource;
use App\Models\ApiToken;
use App\Models\Entry;
use App\Models\User;
use App\QueryFilters\DateFilterCallback;
use App\QueryFilters\TextFilterCallback;
use App\Services\EntryEnricher;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class EntryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\Entry', only: ['index']),
            new Middleware('can:view,entry', only: ['show']),
            new Middleware('can:create,App\Models\Entry', only: ['create', 'store']),
            new Middleware('can:update,entry', only: ['edit', 'update']),
            new Middleware('can:delete,entry', only: ['destroy']),
            new Middleware('can:entries.mark-as-invoiced', only: ['markAsInvoiced']),
        ];
    }

    public function index(EntryCollectionRequest $request): JsonApiResourceCollection
    {
        $entries = QueryBuilder::for(Entry::class)
            ->allowedFilters([
                AllowedFilter::exact('userId', 'user_id'),
                AllowedFilter::exact('budgetId', 'budget_id'),
                AllowedFilter::callback('startedAt', DateFilterCallback::make('started_at')),
                AllowedFilter::callback('endedAt', DateFilterCallback::make('ended_at')),
                AllowedFilter::exact('hasOvertime', 'has_overtime'),
                AllowedFilter::callback('userFullName', TextFilterCallback::make('user_full_name')),
                AllowedFilter::exact('customerId', 'customer_id'),
                AllowedFilter::callback('ticketNumber', TextFilterCallback::make('ticket_number')),
                AllowedFilter::scope('settlement'),
                AllowedFilter::scope('isInvoiced'),
                AllowedFilter::scope('isInvoiceable'),
            ])
            ->allowedIncludes([
                'customer',
                'personalOvertime',
                'customerOvertime',
                'correctionEntryCorrection',
                'correctedEntryCorrection',
                'newEntryCorrection',
                'budget',
            ])
            ->allowedSorts([
                'id',
                AllowedSort::field('startedAt', 'started_at'),
                AllowedSort::field('createdAt', 'created_at'),
                AllowedSort::field('customerName', 'customer_name'),
                AllowedSort::field('ticketNumber', 'ticket_number'),
                AllowedSort::field('minutesSpent', 'minutes_spent'),
                AllowedSort::field('userFullName', 'user_full_name'),
            ])
            ->jsonPaginate();

        return EntryResource::collection($entries);
    }

    /**
     * @throws AuthorizationException
     */
    public function store(
        EntryCreateRequest $request,
        EntryEnricher $entryEnricher
    ): EntryResource {
        /** @var Entry $entry */
        $entry = Entry::query()->newModelInstance($request->validatedAttributes());

        /** @var User|ApiToken $user */
        $user = $request->user();

        if (! ($user instanceof ApiToken)) {
            $entry->created_by_user_id = $user->id;
            $entry->created_by_user_email = $user->email;
            $entry->created_by_user_full_name = $user->full_name;
        }

        if (! $entry->user_full_name && ! $entry->user_email && ! $entry->user_id) {
            $entry->user_id = $entry->created_by_user_id;
            $entry->user_email = $entry->created_by_user_email;
            $entry->user_full_name = $entry->created_by_user_full_name;
        }

        Gate::authorize('creating', $entry);

        $entry->save();

        $entryEnricher->enrichFromRequest($entry, $request);

        return new EntryResource($entry);
    }

    public function show(Entry $entry): EntryResource
    {
        $entry->load(['personalOvertime', 'customerOvertime']);

        return new EntryResource($entry);
    }

    public function update(
        EntryUpdateRequest $request,
        Entry $entry,
        EntryEnricher $entryEnricher
    ): EntryResource {
        $attributes = $request->validatedAttributes();

        if ($attributes['is_internal'] ?? false) {
            // Explicitly set budgetId to null when isInternal is true
            $attributes['budget_id'] = null;
        }

        $entry->fill($attributes);
        $entry->save();

        $entryEnricher->enrichFromRequest($entry, $request);

        return new EntryResource($entry);
    }

    public function markAsInvoiced(
        Entry $entry
    ): EntryResource {
        if ($entry->is_internal) {
            abort(422, 'Cannot invoice internal entry.');
        }

        $entry->invoiced_at = Carbon::now();
        $entry->save();

        return new EntryResource($entry);
    }

    /**
     * @throws Exception
     */
    public function destroy(Entry $entry): Response
    {
        $entry->delete();

        return response()->noContent();
    }
}
