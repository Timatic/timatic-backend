<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Integrations\Contracts\SuggestionIntegrationInterface;
use Illuminate\Support\Collection;

class SuggestionIntegrationRegistry
{
    /** @var array<string, class-string<SuggestionIntegrationInterface>> */
    private array $classes = [];

    /** @param class-string<SuggestionIntegrationInterface> $integrationClass */
    public function register(string $type, string $integrationClass): void
    {
        $this->classes[$type] = $integrationClass;
    }

    /** @return Collection<string, SuggestionIntegrationInterface> */
    public function available(): Collection
    {
        return collect($this->classes)
            ->map(fn (string $class): SuggestionIntegrationInterface => app($class))
            ->filter(fn (SuggestionIntegrationInterface $integration): bool => $integration->isAvailable());
    }
}
