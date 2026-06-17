<?php

namespace App\Services;

use App\Models\Correction;
use App\Models\Entry;
use Carbon\Carbon;

class CorrectEntryService
{
    public function correctEntry(Entry $entryToCorrect, ?int $newEntryId = null): Correction
    {
        $correctionEntry = new Entry(
            $entryToCorrect->only([
                'ticket_id',
                'ticket_type',
                'ticket_title',
                'ticket_number',
                'customer_id',
                'customer_name',
                'hourly_rate',
                'budget_id',
                'user_id',
                'user_email',
                'user_full_name',
                'is_internal',
            ])
        );

        $correctionEntry->entry_type = 'correction';
        $correctionEntry->is_locked = true;
        $correctionEntry->description = 'Correction of entry '.$entryToCorrect->id;

        $correctionEntry->started_at = Carbon::now();

        $startedAtCopy = $correctionEntry->started_at->copy();

        if ($entryToCorrect->minutes_spent > 0) {
            $correctionEntry->ended_at = $startedAtCopy->subMinutes($entryToCorrect->minutes_spent);
        } else {
            $correctionEntry->ended_at = $startedAtCopy->addMinutes(abs((int) $entryToCorrect->minutes_spent));
        }

        $correctionEntry->minutes_spent = $entryToCorrect->minutes_spent * -1;

        $correctionEntry->save();

        $correction = new Correction;
        $correction->corrected_entry_id = $entryToCorrect->id;
        $correction->correction_entry_id = $correctionEntry->id;
        $correction->new_entry_id = $newEntryId;
        $correction->save();

        $entryToCorrect->is_locked = true;
        $entryToCorrect->save();

        return $correction;
    }
}
