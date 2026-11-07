<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadExportController
{
    #[ExcludeRouteFromDocs]
    public function __invoke(string $fileName): StreamedResponse|JsonResponse
    {
        if (! Storage::exists($fileName)) {
            return response()->json(['message' => __('Bestand niet gevonden')], Response::HTTP_NOT_FOUND);
        }

        return Storage::download($fileName);
    }
}
