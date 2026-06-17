<?php

namespace App\Http\Controllers;

use App\Http\Requests\OvertimeCollectionRequest;
use App\Http\Resources;
use App\Models\ApiToken;
use App\Models\Overtime;
use App\Models\OvertimeType;
use App\Models\User;
use App\QueryFilters\DateFilterCallback;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OvertimeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:overtimes.mark-as-exported', only: ['markAsExported']),
        ];
    }

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
                'entry',
            ])
            ->jsonPaginate();

        return Resources\Overtime::collection($overtimes);
    }

    /**
     * @throws AuthorizationException
     * @throws Exception
     */
    public function approve(Overtime $overtime, Request $request): Resources\Overtime
    {
        /** @var User $user */
        $user = $request->user();

        assert(! is_null($user->id));

        Gate::authorize('approve', [$overtime]);

        $overtime->approve($user->id);

        return new Resources\Overtime($overtime);
    }

    public function markAsExported(Overtime $overtime): Resources\Overtime
    {
        $overtime->exported_at = Carbon::now();
        $overtime->save();

        return new Resources\Overtime($overtime);
    }
}
