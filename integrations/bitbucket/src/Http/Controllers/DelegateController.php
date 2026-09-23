<?php

namespace Timatic\Bitbucket\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Timatic\Bitbucket\Connector;
use Timatic\Bitbucket\DataTransferObjects\BitbucketWebhook;
use Timatic\Bitbucket\DataTransferObjects\BitbucketWorkspace;
use Timatic\Bitbucket\OAuthService;
use Timatic\Bitbucket\Requests\GetWorkspacesRequest;
use Timatic\Bitbucket\Requests\RegisterWorkspaceWebhookRequest;

class DelegateController
{
    public function show(Request $request, string $token): View
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        if ($this->isConfigured($integration)) {
            return view('bitbucket::consent.webhook-installed', [
                'justInstalled' => $request->has('webhook_installed'),
            ]);
        }

        if ($this->isConnected($integration)) {
            $integration = app(OAuthService::class)->refreshIfExpired($integration);

            return view('bitbucket::consent.select-workspace', [
                'integration' => $integration,
                'workspaces' => $this->workspaces($integration),
                'justConnected' => $request->has('connected'),
                'webhookFailed' => $request->query('error') === 'webhook_failed',
            ]);
        }

        return view('bitbucket::consent.connect', [
            'integration' => $integration,
        ]);
    }

    public function oauthRedirect(string $token): RedirectResponse
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        if ($this->isConfigured($integration)) {
            return redirect()->route('bitbucket.delegate.show', $token);
        }

        $integration->update([
            'config' => array_merge($integration->config ?? [], ['delegate_return_token' => $token]),
        ]);

        return redirect(app(OAuthService::class)->buildAuthorizationUrl($integration));
    }

    public function installWebhook(Request $request, string $token): RedirectResponse
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        if ($this->isConfigured($integration)) {
            return redirect()->route('bitbucket.delegate.show', $token);
        }

        $request->validate(['workspace_slug' => 'required|string|max:255']);

        $integration = app(OAuthService::class)->refreshIfExpired($integration);

        $config = $integration->config ?? [];
        $config['workspace'] = $request->string('workspace_slug')->toString();
        $config['webhook_secret'] ??= Str::random(32);

        $integration->update(['config' => $config]);

        $webhookUrl = rtrim(config('app.admin_url'), '/').'/integrations/bitbucket/webhook/'.$integration->id;

        $response = new Connector($config)
            ->send(new RegisterWorkspaceWebhookRequest(
                $config['workspace'],
                $webhookUrl,
                $config['webhook_secret'],
            ));

        if ($response->successful()) {
            /** @var BitbucketWebhook $webhook */
            $webhook = $response->dto();

            $integration->update([
                'config' => array_merge($config, ['webhook_uuid' => $webhook->uuid]),
            ]);

            return redirect(route('bitbucket.delegate.show', $token).'?webhook_installed=1');
        }

        return redirect(route('bitbucket.delegate.show', $token).'?error=webhook_failed');
    }

    private function isConfigured(Integration $integration): bool
    {
        return filled(($integration->config ?? [])['webhook_uuid'] ?? null);
    }

    private function isConnected(Integration $integration): bool
    {
        return filled(($integration->config ?? [])['access_token'] ?? null);
    }

    /**
     * @return Collection<int, BitbucketWorkspace>
     */
    private function workspaces(Integration $integration): Collection
    {
        $response = new Connector($integration->config ?? [])
            ->send(new GetWorkspacesRequest);

        /** @var array<int, BitbucketWorkspace> $workspaces */
        $workspaces = $response->dto() ?? [];

        return collect($workspaces);
    }
}
