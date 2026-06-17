<?php

namespace App\Events;

use App\Models\Entry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MinutesSpentSetOnEntry
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(
        protected Entry $entry
    ) {
        //
    }

    public function getEntry(): Entry
    {
        return $this->entry;
    }
}
