<?php

namespace Timatic\GitHub;

use App\Models\Integration;
use Illuminate\Support\Str;
use Timatic\GitHub\Exceptions\GitHubException;
use Timatic\GitHub\Models\RepositoryMapping;
use Timatic\GitHub\Requests\CreateRepositoryWebhookRequest;
use Timatic\GitHub\Requests\DeleteRepositoryWebhookRequest;

/**
 * Each mapped repository gets its own webhook pointing at this tenant, so deliveries
 * need no central routing and unmapped repositories send nothing.
 */
class RepositoryWebhookService
{
    private const EVENTS = [
        'push',
        'pull_request',
        'pull_request_review',
        'pull_request_review_comment',
        'issue_comment',
        'create',
        'repository',
    ];

    /** @throws GitHubException */
    public function sync(RepositoryMapping $mapping): void
    {
        if ($mapping->customer_id !== null && $mapping->webhook_id === null) {
            $this->create($mapping);
        }

        if ($mapping->customer_id === null && $mapping->webhook_id !== null) {
            $this->delete($mapping);
        }
    }

    private function create(RepositoryMapping $mapping): void
    {
        $response = Connector::forInstallation($mapping->installation_id)->send(new CreateRepositoryWebhookRequest(
            $mapping->repository_full_name,
            route('github.webhook', $mapping->integration_id),
            $this->secret($mapping->integration),
            self::EVENTS,
        ));

        if ($response->failed()) {
            throw new GitHubException(sprintf(
                'Creating the webhook on %s failed with status %d.',
                $mapping->repository_full_name,
                $response->status(),
            ));
        }

        $mapping->update(['webhook_id' => $response->dto()]);
    }

    /**
     * A webhook someone already removed on GitHub counts as deleted.
     */
    private function delete(RepositoryMapping $mapping): void
    {
        $response = Connector::forInstallation($mapping->installation_id)
            ->send(new DeleteRepositoryWebhookRequest($mapping->repository_full_name, (int) $mapping->webhook_id));

        if ($response->failed() && $response->status() !== 404) {
            throw new GitHubException(sprintf(
                'Deleting the webhook on %s failed with status %d.',
                $mapping->repository_full_name,
                $response->status(),
            ));
        }

        $mapping->update(['webhook_id' => null]);
    }

    private function secret(Integration $integration): string
    {
        $secret = $integration->config['webhook_secret'] ?? null;

        if ($secret !== null) {
            return $secret;
        }

        $secret = Str::random(40);
        $integration->update(['config' => array_merge($integration->config ?? [], ['webhook_secret' => $secret])]);

        return $secret;
    }
}
