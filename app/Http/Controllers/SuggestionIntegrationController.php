<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\SuggestionIntegration;
use App\Http\Resources\SuggestionIntegration as SuggestionIntegrationResource;
use App\Integrations\Contracts\SuggestionIntegrationInterface;
use App\Integrations\SuggestionIntegrationRegistry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class SuggestionIntegrationController extends Controller
{
    /**
     * The integrations that turn a user's work into suggestions, with whether they are active for
     * the current user. Company managed integrations only show up once an admin switched them on.
     */
    public function __invoke(SuggestionIntegrationRegistry $registry, #[CurrentUser] User $user): JsonApiResourceCollection
    {
        $integrations = $registry->available()
            ->map(fn (SuggestionIntegrationInterface $integration, string $key): SuggestionIntegration => new SuggestionIntegration(
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
