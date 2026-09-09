<?php

namespace Timatic\GitHub\Http\Controllers;

use App\Filament\Resources\Integrations\IntegrationResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;
use Timatic\GitHub\OAuthService;

class CallbackController
{
    public function __invoke(Request $request, OAuthService $oauth): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect(IntegrationResource::getUrl('index'))
                ->with('github_error', 'GitHub verbinding geweigerd: '.$request->string('error_description'));
        }

        try {
            $integration = $oauth->handleCallback(
                $request->string('code'),
                $request->string('state'),
            );

            return redirect(IntegrationResource::getUrl('index'))
                ->with('github_success', 'GitHub verbinding succesvol voor '.$integration->name.'.');
        } catch (Throwable $e) {
            return redirect(IntegrationResource::getUrl('index'))
                ->with('github_error', 'Verbinding mislukt: '.$e->getMessage());
        }
    }
}
