<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExportFormat as ExportFormatResource;
use App\Integrations\Exports\ExportService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExportFormatController extends Controller
{
    public function __invoke(ExportService $exportService): AnonymousResourceCollection
    {
        return ExportFormatResource::collection($exportService->formats());
    }
}
