<?php

namespace App\Http\Controllers;

use App\Http\Resources\DailyProgress as DailyProgressResource;
use App\Models\DailyProgress;
use App\Models\User;
use App\Services\DailyProgressCalculator;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class GetDailyProgressController extends Controller
{
    /**
     * @return AnonymousResourceCollection
     *
     * @throws Exception
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(
        Request $request,
        DailyProgressCalculator $progressCalculator
    ) {
        /** @var Authenticatable $user */
        $user = $request->user();

        if (! ($user instanceof User)) {
            throw new Exception('Machine to machine usage for this endpoint is currently not supported.');
        }

        $userId = $user->id;

        /** @var array<string, string> $filters */
        $filters = $request->get('filter');

        if (
            ! isset($filters['from']) && ! isset($filters['date'])
            || ! isset($filters['to']) && ! isset($filters['date'])
        ) {
            throw new BadRequestException('Need either filter[date] or filter[from] and filter[to]');
        }

        $from = Carbon::parse($filters['from'] ?? $filters['date'], config('timatic.preferred_timezone'));
        $to = Carbon::parse($filters['to'] ?? $filters['date'], config('timatic.preferred_timezone'));

        $date = $from->copy();

        $progressCollection = new Collection;

        do {
            $progressCollection->add(
                $progressCalculator->calculate(
                    new DailyProgress([
                        'userId' => $userId,
                        'date' => $date->copy(),
                    ])
                )
            );

            $date->addDay();
        } while (! $date->greaterThan($to));

        return DailyProgressResource::collection($progressCollection);
    }
}
