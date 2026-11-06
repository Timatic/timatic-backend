<?php

namespace Timatic\GitHub\Jobs;

class ProcessRepositoryWebhookJob extends ProcessWebhookJob
{
    /** @var array<int, string> */
    private const array REPOSITORY_ACTIONS = [
        'created',
        'deleted',
        'archived',
        'unarchived',
        'publicized',
        'privatized',
        'edited',
        'renamed',
        'transferred',
    ];

    public function handle(): void
    {
        if (! in_array($this->action, self::REPOSITORY_ACTIONS, strict: true)) {
            return;
        }

        $user = $this->senderUser();

        if ($user === null) {
            return;
        }

        $repository = $this->payload['repository'] ?? [];
        $timestamp = $repository['updated_at'] ?? null;

        $this->createEvent(
            user: $user,
            eventTypeId: 'repository_'.$this->action,
            title: $this->repositoryTitle(),
            timestamp: $this->parseTimestamp($timestamp),
            ticket: null,
            externalId: 'repository:'.$this->action.':'.($repository['id'] ?? '').':'.$timestamp,
        );
    }

    private function repositoryTitle(): string
    {
        $repositoryFullName = $this->repositoryFullName();
        $previousName = $this->payload['changes']['repository']['name']['from'] ?? null;

        if ($this->action === 'renamed' && is_string($previousName)) {
            return 'Repository renamed: '.$previousName.' to '.$repositoryFullName;
        }

        return 'Repository '.$this->action.': '.$repositoryFullName;
    }
}
