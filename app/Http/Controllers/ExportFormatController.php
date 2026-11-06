<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExportFormat as ExportFormatResource;
use App\Integrations\Exports\ExportService;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class ExportFormatController extends Controller
{
    public function __invoke(ExportService $exportService): JsonApiResourceCollection
    {
        return ExportFormatResource::collection($exportService->formats());
    }
}
