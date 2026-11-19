<?php

namespace App\Events;

use App\Models\Entry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntryDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(protected Entry $entry) {}

    public function getEntry(): Entry
    {
        return $this->entry;
    }
}
