<?php

namespace Timatic\Jira\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Timatic\Jira\OAuthService;

class DelegateController
{
    public function show(string $token): View
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        return view('jira::delegate.show', [
            'integration' => $integration,
            'configured' => $this->isConfigured($integration),
        ]);
    }

    public function oauthRedirect(string $token): RedirectResponse
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        if ($this->isConfigured($integration)) {
            return redirect()->route('jira.delegate.show', $token);
        }

        $integration->update([
            'config' => array_merge($integration->config ?? [], ['delegate_return_token' => $token]),
        ]);

        return redirect(app(OAuthService::class)->buildAuthorizationUrl($integration));
    }

    private function isConfigured(Integration $integration): bool
    {
        $config = $integration->config ?? [];

        return filled($config['access_token'] ?? null) && filled($config['cloud_id'] ?? null);
    }
}
