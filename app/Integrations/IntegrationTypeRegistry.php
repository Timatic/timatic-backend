<?php

namespace App\Integrations;

use App\Models\Integration;
use Closure;

class IntegrationTypeRegistry
{
    /** @var array<string, array<string, class-string>> */
    private array $pageEntries = [];

    /** @var array<string, Closure(Integration): class-string> */
    private array $landingResolvers = [];

    private string $lastType = '';

    /**
     * @param  array<string, class-string>  $pages  [routeSlug => pageClass]
     */
    public function register(string $type, array $pages): static
    {
        $this->pageEntries[$type] = $pages;
        $this->lastType = $type;

        return $this;
    }

    /** @param  Closure(Integration): class-string  $resolver */
    public function landingPage(Closure $resolver): static
    {
        $this->landingResolvers[$this->lastType] = $resolver;

        return $this;
    }

    /** @return class-string|null */
    public function resolveLandingPageClass(string $type, Integration $integration): ?string
    {
        if (isset($this->landingResolvers[$type])) {
            return ($this->landingResolvers[$type])($integration);
        }

        return array_values($this->pageEntries[$type] ?? [])[0] ?? null;
    }

    /** @return array<string, class-string> */
    public function allPageEntries(): array
    {
        return array_merge(...array_values($this->pageEntries));
    }
}
