<?php

namespace App\Http\Controllers\Exports;

use App\DataTransferObjects\ExportPeriod;
use App\Enums\HoursExportDimension;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRequest;
use App\Jobs\ExportBudgetsJob;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExportEmailController extends Controller
{
    /**
     * Queue an export.
     *
     * The export is built in the background and mailed to the current user as a download link.
     * `GET /export-formats` lists the export types and which period parameters each one needs.
     *
     * @return JsonResponse
     */
    public function __invoke(ExportRequest $request, #[CurrentUser] User $user)
    {
        ExportBudgetsJob::dispatch($user, $request->validated('exportType'), new ExportPeriod(
            year: $request->validated('year'),
            month: $request->validated('month'),
            fromMonth: $request->validated('fromMonth'),
            toMonth: $request->validated('toMonth'),
            groupBy: $request->enum('groupBy', HoursExportDimension::class),
            splitBy: $request->enum('splitBy', HoursExportDimension::class),
            customerId: $request->validated('customerId'),
            budgetId: $request->validated('budgetId'),
            userId: $request->validated('userId'),
        ));

        return response()->json(['message' => __('Export gestart. Je ontvangt een e-mail zodra het bestand klaar is.')], Response::HTTP_ACCEPTED);
    }
}
