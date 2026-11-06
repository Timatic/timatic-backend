<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\Entry as EntryResource;
use App\Models\Entry;
use Carbon\Carbon;

class MarkEntryAsInvoicedController
{
    public function __invoke(Entry $entry): EntryResource
    {
        if ($entry->is_internal) {
            abort(422, 'Cannot invoice internal entry.');
        }

        $entry->invoiced_at = Carbon::now();
        $entry->save();

        return new EntryResource($entry);
    }
}
