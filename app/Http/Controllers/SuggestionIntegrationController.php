<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\SuggestionIntegrationStatus;
use App\Http\Resources\SuggestionIntegration as SuggestionIntegrationResource;
use App\Integrations\Contracts\SuggestionIntegrationInterface;
use App\Integrations\SuggestionIntegrationRegistry;
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
        $statuses = $registry->available()
            ->map(fn (SuggestionIntegrationInterface $integration, string $key): SuggestionIntegrationStatus => new SuggestionIntegrationStatus(
                key: $key,
                label: $integration->label(),
                instructions: $integration->instructions(),
                isCompanyManaged: $integration->isCompanyManaged(),
                isActive: $integration->isActiveFor($user),
                connectUrl: $integration->connectUrl($user),
            ))
            ->values();

        return SuggestionIntegrationResource::collection($statuses);
    }
}
