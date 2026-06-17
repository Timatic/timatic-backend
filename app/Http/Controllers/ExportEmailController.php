<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportRequest;
use App\Jobs\ExportBudgetsJob;
use App\Models\User;
use App\Services\BudgetUsageService;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportEmailController extends Controller
{
    /**
     * @return JsonResponse
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(ExportRequest $request, BudgetUsageService $usageService, #[CurrentUser] User $user)
    {
        $validated = $request->validated();

        $exportType = $validated['exportType'];
        $year = $validated['year'];
        $month = $validated['month'] ?? null;

        ExportBudgetsJob::dispatch($user, $exportType, $year, $month, $usageService);

        return response()->json(['message' => __('Export gestart. Je ontvangt een e-mail zodra het bestand klaar is.')], Response::HTTP_ACCEPTED);
    }

    /**
     * @return StreamedResponse|JsonResponse
     */
    #[ExcludeRouteFromDocs]
    public function download(string $fileName)
    {
        if (! Storage::exists($fileName)) {
            return response()->json(['message' => __('Bestand niet gevonden')], Response::HTTP_NOT_FOUND);
        }

        return Storage::download($fileName);

    }
}
