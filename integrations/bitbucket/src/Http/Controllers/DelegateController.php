<?php

namespace Timatic\Bitbucket\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Timatic\Bitbucket\Connector;
use Timatic\Bitbucket\DataTransferObjects\BitbucketWebhook;
use Timatic\Bitbucket\OAuthService;
use Timatic\Bitbucket\Requests\GetWorkspacesRequest;
use Timatic\Bitbucket\Requests\RegisterWorkspaceWebhookRequest;

class DelegateController
{
    public function show(string $token): View
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        if (! $integration->isShareTokenValid()) {
            return view('bitbucket::delegate.show', ['integration' => $integration, 'workspaces' => [], 'expired' => true]);
        }

        $config = $integration->config ?? [];
        $workspaces = [];

        if (filled($config['access_token'] ?? null)) {
            $integration = app(OAuthService::class)->refreshIfExpired($integration);

            $response = new Connector($integration->config ?? [])
                ->send(new GetWorkspacesRequest);

            $workspaces = $response->dto() ?? [];
        }

        return view('bitbucket::delegate.show', ['integration' => $integration, 'workspaces' => $workspaces, 'expired' => false]);
    }

    public function oauthRedirect(string $token): RedirectResponse
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        if (! $integration->isShareTokenValid()) {
            return redirect()->route('bitbucket.delegate.show', $token)->with('error', 'link_expired');
        }

        $integration->update([
            'config' => array_merge($integration->config ?? [], ['delegate_return_token' => $token]),
        ]);

        return redirect(app(OAuthService::class)->buildAuthorizationUrl($integration));
    }

    public function installWebhook(Request $request, string $token): RedirectResponse
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        if (! $integration->isShareTokenValid()) {
            return redirect()->route('bitbucket.delegate.show', $token)->with('error', 'link_expired');
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
}
