<?php

namespace App\Events;

use App\Models\Activity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityCreated
{
    use Dispatchable, SerializesModels;

    protected Activity $activity;

    /**
     * Create a new activity instance.
     *
     * @return void
     */
    public function __construct(Activity $activity)
    {
        $this->activity = $activity;
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }
}
