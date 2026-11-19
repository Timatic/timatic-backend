<?php

namespace Timatic\Jira;

use App\Models\Integration;
use Closure;

/**
 * Lets another integration take over the worklog sync of a Jira instance,
 * so the scheduled Jira sync leaves that instance alone.
 */
class WorklogSourceRegistry
{
    /** @var array<int, Closure(Integration): bool> */
    private array $claims = [];

    /** @param Closure(Integration): bool $claim */
    public function claim(Closure $claim): void
    {
        $this->claims[] = $claim;
    }

    public function isClaimed(Integration $jiraIntegration): bool
    {
        foreach ($this->claims as $claim) {
            if ($claim($jiraIntegration)) {
                return true;
            }
        }

        return false;
    }
}
