<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportRequest;
use App\Jobs\ExportBudgetsJob;
use App\Models\User;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExportEmailController extends Controller
{
    /**
     * @return JsonResponse
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(ExportRequest $request, #[CurrentUser] User $user)
    {
        $validated = $request->validated();

        $exportType = $validated['exportType'];
        $year = $validated['year'] ?? null;
        $month = $validated['month'] ?? null;

        ExportBudgetsJob::dispatch($user, $exportType, $year, $month);

        return response()->json(['message' => __('Export gestart. Je ontvangt een e-mail zodra het bestand klaar is.')], Response::HTTP_ACCEPTED);
    }
}
