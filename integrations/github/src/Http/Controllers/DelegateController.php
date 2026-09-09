<?php

namespace Timatic\GitHub\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Timatic\GitHub\Connector;
use Timatic\GitHub\DataTransferObjects\GitHubInstallation;
use Timatic\GitHub\OAuthService;
use Timatic\GitHub\Requests\GetUserInstallationsRequest;

class DelegateController
{
    public function show(string $token): View
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();
        $config = $integration->config ?? [];
        $installations = [];

        if (filled($config['access_token'] ?? null) && ! $this->isConfigured($integration)) {
            $installations = $this->fetchInstallations($integration);
        }

        return view('github::delegate.show', [
            'integration' => $integration,
            'installations' => $installations,
            'installUrl' => app(OAuthService::class)->installUrl(),
            'configured' => $this->isConfigured($integration),
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

    public function chooseInstallation(Request $request, string $token): RedirectResponse
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        if ($this->isConfigured($integration)) {
            return redirect()->route('github.delegate.show', $token);
        }

        $request->validate(['installation_id' => 'required|integer|min:1']);

        $installationId = $request->integer('installation_id');
        $installation = collect($this->fetchInstallations($integration))
            ->first(fn (GitHubInstallation $installation) => $installation->id === $installationId);

        if ($installation === null) {
            return redirect(route('github.delegate.show', $token).'?error=installation_invalid');
        }

        $integration->update([
            'config' => array_merge($integration->config ?? [], [
                'installation_id' => $installation->id,
                'installation_account' => $installation->accountLogin,
            ]),
        ]);

        return redirect(route('github.delegate.show', $token).'?installation_selected=1');
    }

    /** @return array<int, GitHubInstallation> */
    private function fetchInstallations(Integration $integration): array
    {
        $integration = app(OAuthService::class)->refreshIfExpired($integration);
        $response = Connector::forUser($integration->config['access_token'] ?? '')
            ->send(new GetUserInstallationsRequest);

        return $response->failed() ? [] : $response->dto();
    }

    private function isConfigured(Integration $integration): bool
    {
        return filled(($integration->config ?? [])['installation_id'] ?? null);
    }
}
