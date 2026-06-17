<?php

namespace App\Http\Controllers;

use App\Http\Requests\CorrectionCreateRequest;
use App\Http\Requests\CorrectionUpdateRequest;
use App\Http\Resources;
use App\Models\Correction;
use App\Models\Entry;
use App\Services\CorrectEntryService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CorrectionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\Correction', only: ['index']),
            new Middleware('can:view,correction', only: ['show']),
            new Middleware('can:create,App\Models\Correction', only: ['create', 'store']),
            new Middleware('can:update,correction', only: ['edit', 'update']),
            new Middleware('can:delete,correction', only: ['destroy']),
        ];
    }

    public function store(CorrectionCreateRequest $request, CorrectEntryService $correctionEntryService): Resources\Correction
    {
        /** @var Entry $entryToCorrect */
        $entryToCorrect = Entry::query()->find($request->validatedAttributes()['corrected_entry_id']);

        $correction = $correctionEntryService->correctEntry(
            $entryToCorrect,
            $request->input('data.attributes.newEntryId')
        );

        $correction->load([
            'correctedEntry',
            'correctionEntry',
            'newEntry',
        ]);

        return new Resources\Correction($correction);
    }

    public function update(CorrectionUpdateRequest $request, Correction $correction): Resources\Correction
    {
        $correction->update($request->validatedAttributes());

        return new Resources\Correction($correction);
    }
}
