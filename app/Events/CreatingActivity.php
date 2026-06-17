<?php

namespace App\Events;

use App\Models\Activity;

class CreatingActivity
{
    public function __construct(protected Activity $activity) {}

    public function getActivity(): Activity
    {
        return $this->activity;
    }
}
