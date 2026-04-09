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

        if (! $integration->isShareTokenValid()) {
            return view('jira::delegate.show', ['integration' => $integration, 'expired' => true]);
        }

        return view('jira::delegate.show', ['integration' => $integration, 'expired' => false]);
    }

    public function oauthRedirect(string $token): RedirectResponse
    {
        $integration = Integration::where('share_token', $token)->firstOrFail();

        if (! $integration->isShareTokenValid()) {
            return redirect()->route('jira.delegate.show', $token)->with('error', 'link_expired');
        }

        $integration->update([
            'config' => array_merge($integration->config ?? [], ['delegate_return_token' => $token]),
        ]);

        return redirect(app(OAuthService::class)->buildAuthorizationUrl($integration));
    }
}
