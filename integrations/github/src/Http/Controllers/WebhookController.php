<?php

namespace Timatic\GitHub\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Timatic\GitHub\Jobs\ProcessWebhookJob;
use Timatic\GitHub\Models\RepositoryMapping;

class WebhookController extends Controller
{
    public function __invoke(Request $request, Integration $integration): Response
    {
        if (! $this->signatureIsValid($request)) {
            abort(403);
        }

        $eventName = (string) $request->header('X-GitHub-Event', '');

        if ($eventName === 'ping') {
            return response('', 200);
        }

        $payload = $request->json()->all();

        $mapping = RepositoryMapping::where('integration_id', $integration->id)
            ->where('repository_full_name', $payload['repository']['full_name'] ?? '')
            ->active()
            ->first();

        ProcessWebhookJob::dispatch($payload, $mapping, $eventName, $payload['action'] ?? null);

        return response('', 200);
    }

    /**
     * The secret belongs to the app, not to a single integration, since the app
     * signs every delivery with the same secret before the proxy forwards it.
     */
    private function signatureIsValid(Request $request): bool
    {
        $secret = (string) config('github.webhook_secret');
        $signature = $request->header('X-Hub-Signature-256');

        if ($secret === '' || $signature === null) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }
}
