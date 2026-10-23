<?php

namespace Timatic\GitHub\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Timatic\GitHub\Exceptions\GitHubException;
use Timatic\GitHub\InstallationService;
use Timatic\GitHub\OAuthService;

class DelegateController
{
    public function show(Request $request, string $token): View
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        if (! $this->isConnected($integration)) {
            return view('github::consent.connect', [
                'integration' => $integration,
            ]);
        }

        $integration = $this->refreshInstallations($integration);

        if ($this->isConfigured($integration)) {
            return view('github::consent.connected', [
                'accounts' => app(InstallationService::class)->stored($integration->config ?? []),
                'installUrl' => app(OAuthService::class)->installUrl(),
            ]);
        }

        return view('github::consent.install', [
            'installUrl' => app(OAuthService::class)->installUrl(),
            'justConnected' => $request->has('connected'),
        ]);
    }

    public function oauthRedirect(string $token): RedirectResponse
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        if ($this->isConfigured($integration)) {
            return redirect()->route('github.delegate.show', $token);
        }

        $integration->update([
            'config' => array_merge($integration->config ?? [], ['delegate_return_token' => $token]),
        ]);

        return redirect(app(OAuthService::class)->buildAuthorizationUrl($integration));
    }

    /**
     * The delegate has nothing to choose: every installation they can reach is adopted,
     * and the list is refreshed on each visit so a fresh install shows up.
     */
    private function refreshInstallations(Integration $integration): Integration
    {
        try {
            app(InstallationService::class)->refresh($integration);
        } catch (GitHubException $e) {
            return $integration;
        }

        return $integration->refresh();
    }

    private function isConnected(Integration $integration): bool
    {
        return filled(($integration->config ?? [])['access_token'] ?? null);
    }

    private function isConfigured(Integration $integration): bool
    {
        return app(InstallationService::class)->stored($integration->config ?? []) !== [];
    }
}
