<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources;
use App\Models\Overtime;
use App\Models\User;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApproveOvertimeController
{
    /**
     * @throws AuthorizationException
     * @throws Exception
     */
    public function __invoke(Overtime $overtime, Request $request): Resources\Overtime
    {
        /** @var User $user */
        $user = $request->user();

        assert(! is_null($user->id));

        Gate::authorize('approve', [$overtime]);

        $overtime->approve($user->id);

        return new Resources\Overtime($overtime);
    }
}
