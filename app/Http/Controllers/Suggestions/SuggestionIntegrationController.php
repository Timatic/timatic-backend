<?php

declare(strict_types=1);

namespace App\Http\Controllers\Suggestions;

use App\DataTransferObjects\SuggestionIntegration;
use App\Http\Controllers\Controller;
use App\Http\Resources\SuggestionIntegration as SuggestionIntegrationResource;
use App\Integrations\Suggestions\SuggestionProviderInterface;
use App\Integrations\Suggestions\SuggestionProviderRegistry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SuggestionIntegrationController extends Controller
{
    /**
     * The integrations that turn a user's work into suggestions, with whether they are active for
     * the current user. Company managed integrations only show up once an admin switched them on.
     */
    public function __invoke(SuggestionProviderRegistry $registry, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        $integrations = $registry->available()
            ->map(fn (SuggestionProviderInterface $integration, string $key): SuggestionIntegration => new SuggestionIntegration(
                key: $key,
                label: $integration->label(),
                iconUrl: Source::iconUrlFor($integration->sourceId()),
                instructions: $integration->instructions(),
                isCompanyManaged: $integration->isCompanyManaged(),
                isActive: $integration->isActiveFor($user),
                connectUrl: $integration->connectUrl($user),
                connectLabel: $integration->connectLabel(),
            ))
            ->values();

        return SuggestionIntegrationResource::collection($integrations);
    }
}
