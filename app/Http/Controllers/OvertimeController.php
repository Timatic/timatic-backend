<?php

namespace App\Http\Controllers;

use App\Http\Requests\OvertimeCollectionRequest;
use App\Http\Resources;
use App\Models\ApiToken;
use App\Models\Overtime;
use App\Models\OvertimeType;
use App\Models\User;
use App\QueryFilters\DateFilterCallback;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\Includes\IncludedRelationship;
use Spatie\QueryBuilder\QueryBuilder;

class OvertimeController extends Controller
{
    public function index(OvertimeCollectionRequest $request): AnonymousResourceCollection
    {
        $query = Overtime::query()
            ->has('entry')
            ->where('overtime_type_id', OvertimeType::PERSONAL);

        /** @var User|ApiToken $user */
        $user = $request->user();

        if ($user instanceof ApiToken) {
            Gate::authorize('viewAll', Overtime::class);
        }

        if ($user instanceof User && $user->cannot('viewAll', Overtime::class)) {
            $query->whereIn('entry_id', function ($query) use ($user) {
                $query->select('id')
                    ->from('entries')
                    ->where('user_id', $user->id);
            });
        }

        $overtimes = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::callback('startedAt', DateFilterCallback::make('started_at')),
                AllowedFilter::callback('endedAt', DateFilterCallback::make('ended_at')),
                AllowedFilter::scope('isApproved'),
                AllowedFilter::callback('approvedAt', DateFilterCallback::make('approved_at')),
                AllowedFilter::scope('isExported'),
            ])
            ->allowedIncludes([
                AllowedInclude::custom('entry', new IncludedRelationship),
            ])
            ->jsonPaginate();

        return Resources\Overtime::collection($overtimes);
    }
}
