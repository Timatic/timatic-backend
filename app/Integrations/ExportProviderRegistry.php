<?php

namespace App\Integrations;

use App\Integrations\Contracts\ExportProviderInterface;

class ExportProviderRegistry
{
    /** @var array<string, list<class-string<ExportProviderInterface>>> */
    private array $classes = [];

    /** @param class-string<ExportProviderInterface> $providerClass */
    public function register(string $type, string $providerClass): void
    {
        $this->classes[$type][] = $providerClass;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<ExportProviderInterface>
     */
    public function makeProviders(string $type, array $config): array
    {
        return array_map(
            fn (string $class): ExportProviderInterface => $class::fromConfig($config),
            $this->classes[$type] ?? [],
        );
    }

    /** @return list<string> */
    public function registeredTypes(): array
    {
        return array_keys($this->classes);
    }
}
