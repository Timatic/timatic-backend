<?php

namespace Timatic\Jira\DataTransferObjects;

use Illuminate\Support\Collection;

readonly class JiraIssueDetails
{
    /**
     * @param  Collection<int, JiraComment>  $comments
     * @param  Collection<int, JiraChangelogEntry>  $statusChanges
     */
    public function __construct(
        public JiraIssue $issue,
        public Collection $comments,
        public Collection $statusChanges,
    ) {}
}
