<?php

namespace App\Integrations;

use App\Integrations\Contracts\TicketProviderInterface;
use InvalidArgumentException;

class TicketProviderRegistry
{
    /** @var array<string, class-string<TicketProviderInterface>> */
    private array $classes = [];

    /** @param class-string<TicketProviderInterface> $providerClass */
    public function register(string $type, string $providerClass): void
    {
        $this->classes[$type] = $providerClass;
    }

    /** @param array<string, mixed> $config */
    public function makeProvider(string $type, array $config): TicketProviderInterface
    {
        $class = $this->classes[$type]
            ?? throw new InvalidArgumentException("Integration type [{$type}] is not registered.");

        return $class::fromConfig($config);
    }

    /** @return array<string, class-string<TicketProviderInterface>> */
    public function all(): array
    {
        return $this->classes;
    }
}
