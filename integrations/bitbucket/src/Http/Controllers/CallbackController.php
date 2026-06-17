<?php

namespace Timatic\Bitbucket\Http\Controllers;

use App\Filament\Resources\Integrations\IntegrationResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Timatic\Bitbucket\Filament\Pages\SettingsPage;
use Timatic\Bitbucket\OAuthService;

class CallbackController
{
    public function __invoke(Request $request, OAuthService $oauth): RedirectResponse
    {
        $integrationId = $this->extractIntegrationId($request->string('state'));

        if ($request->has('error')) {
            return $this->redirectToSettings($integrationId)
                ->with('bitbucket_error', 'Bitbucket verbinding geweigerd: '.$request->string('error_description'));
        }

        try {
            $integration = $oauth->handleCallback(
                $request->string('code'),
                $request->string('state'),
            );

            return redirect(SettingsPage::getUrl(['record' => $integration->getKey()]))
                ->with('bitbucket_success', 'Bitbucket verbinding succesvol.');
        } catch (\Throwable $e) {
            return $this->redirectToSettings($integrationId)
                ->with('bitbucket_error', 'Verbinding mislukt: '.$e->getMessage());
        }
    }

    private function extractIntegrationId(string $state): ?int
    {
        $parts = explode('.', $state);

        if (count($parts) < 2) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), associative: true);

        return isset($payload['integration_id']) && is_numeric($payload['integration_id'])
            ? (int) $payload['integration_id']
            : null;
    }

    private function redirectToSettings(?int $integrationId): RedirectResponse
    {
        if ($integrationId !== null) {
            return redirect(SettingsPage::getUrl(['record' => $integrationId]));
        }

        return redirect(IntegrationResource::getUrl('index'));
    }
}
