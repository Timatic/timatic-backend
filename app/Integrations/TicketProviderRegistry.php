<?php

namespace App\Integrations;

use App\Integrations\Contracts\TicketProviderInterface;

class TicketProviderRegistry
{
    /** @var array<string, list<class-string<TicketProviderInterface>>> */
    private array $classes = [];

    /** @param class-string<TicketProviderInterface> $providerClass */
    public function register(string $type, string $providerClass): void
    {
        $this->classes[$type][] = $providerClass;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<TicketProviderInterface>
     */
    public function makeProviders(string $type, array $config): array
    {
        return array_map(
            fn (string $class): TicketProviderInterface => $class::fromConfig($config),
            $this->classes[$type] ?? [],
        );
    }

    /** @return list<string> */
    public function registeredTypes(): array
    {
        return array_keys($this->classes);
    }

    /** @return list<class-string<TicketProviderInterface>> */
    public function providerClasses(): array
    {
        return array_merge([], ...array_values($this->classes));
    }
}
