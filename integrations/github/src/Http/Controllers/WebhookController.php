<?php

namespace Timatic\GitHub\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Timatic\GitHub\Jobs\ProcessCreateWebhookJob;
use Timatic\GitHub\Jobs\ProcessIssueCommentWebhookJob;
use Timatic\GitHub\Jobs\ProcessPullRequestReviewCommentWebhookJob;
use Timatic\GitHub\Jobs\ProcessPullRequestReviewWebhookJob;
use Timatic\GitHub\Jobs\ProcessPullRequestWebhookJob;
use Timatic\GitHub\Jobs\ProcessPushWebhookJob;
use Timatic\GitHub\Jobs\ProcessRepositoryWebhookJob;
use Timatic\GitHub\Models\RepositoryMapping;

class WebhookController extends Controller
{
    public function __invoke(Request $request, Integration $integration): Response
    {
        if (! $this->signatureIsValid($request, $integration)) {
            abort(403);
        }

        $job = match ($request->header('X-GitHub-Event')) {
            'push' => ProcessPushWebhookJob::class,
            'pull_request' => ProcessPullRequestWebhookJob::class,
            'pull_request_review' => ProcessPullRequestReviewWebhookJob::class,
            'pull_request_review_comment' => ProcessPullRequestReviewCommentWebhookJob::class,
            'issue_comment' => ProcessIssueCommentWebhookJob::class,
            'create' => ProcessCreateWebhookJob::class,
            'repository' => ProcessRepositoryWebhookJob::class,
            default => null,
        };

        if ($job === null) {
            return response('', 200);
        }

        $payload = $request->json()->all();

        $mapping = RepositoryMapping::where('integration_id', $integration->id)
            ->where('repository_full_name', $payload['repository']['full_name'] ?? '')
            ->active()
            ->first();

        $job::dispatch($payload, $mapping, $payload['action'] ?? null);

        return response('', 200);
    }

    private function signatureIsValid(Request $request, Integration $integration): bool
    {
        $secret = (string) ($integration->config['webhook_secret'] ?? '');
        $signature = $request->header('X-Hub-Signature-256');

        if ($secret === '' || $signature === null) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }
}
