<?php

declare(strict_types=1);

namespace App\Integrations\Suggestions;

use Illuminate\Support\Collection;

class SuggestionProviderRegistry
{
    /** @var array<string, class-string<SuggestionProviderInterface>> */
    private array $classes = [];

    /** @param class-string<SuggestionProviderInterface> $providerClass */
    public function register(string $type, string $providerClass): void
    {
        $this->classes[$type] = $providerClass;
    }

    /** @return array<string, class-string<SuggestionProviderInterface>> */
    public function all(): array
    {
        return $this->classes;
    }

    /** @return Collection<string, SuggestionProviderInterface> */
    public function available(): Collection
    {
        return collect($this->classes)
            ->map(fn (string $class): SuggestionProviderInterface => app($class))
            ->filter(fn (SuggestionProviderInterface $provider): bool => $provider->isAvailable());
    }
}
