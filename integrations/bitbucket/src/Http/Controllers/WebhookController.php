<?php

namespace Timatic\Bitbucket\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Timatic\Bitbucket\Jobs\ProcessWebhookJob;
use Timatic\Bitbucket\Models\RepositoryMapping;

class WebhookController extends Controller
{
    public function __invoke(Request $request, Integration $integration): Response
    {
        if (! $this->signatureIsValid($request, $integration)) {
            abort(403);
        }

        $payload = $request->json()->all();

        [$workspaceSlug, $repositorySlug] = $this->extractSlugs($payload);

        if ($workspaceSlug === null || $repositorySlug === null) {
            return response('', 200);
        }

        $mapping = RepositoryMapping::where('integration_id', $integration->id)
            ->where('workspace_slug', $workspaceSlug)
            ->where('repository_slug', $repositorySlug)
            ->active()
            ->first();

        $eventKey = (string) $request->header('X-Event-Key', '');

        ProcessWebhookJob::dispatch($payload, $mapping, $eventKey);

        return response('', 200);
    }

    private function signatureIsValid(Request $request, Integration $integration): bool
    {
        $secret = $integration->config['webhook_secret'] ?? null;

        if ($secret === null) {
            return false;
        }

        $signature = $request->header('X-Hub-Signature');

        if ($signature === null) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: ?string, 1: ?string}
     */
    private function extractSlugs(array $payload): array
    {
        $fullName = $payload['repository']['full_name'] ?? null;

        if (! is_string($fullName) || ! str_contains($fullName, '/')) {
            return [null, null];
        }

        [$workspace, $repo] = explode('/', $fullName, 2);

        return [$workspace, $repo];
    }
}
