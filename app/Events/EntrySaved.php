<?php

namespace App\Events;

use App\Events\Contracts\HasBudget;
use App\Models\Budget;
use App\Models\Entry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntrySaved implements HasBudget
{
    use Dispatchable, SerializesModels;

    protected Entry $entry;

    /**
     * Create a new event instance.
     */
    public function __construct(Entry $entry)
    {
        $this->entry = $entry;
    }

    public function getEntry(): Entry
    {
        return $this->entry;
    }

    public function getBudget(): ?Budget
    {
        if (empty($this->entry->budget_id)) {
            return null;
        }

        return $this->entry->budget;
    }
}
