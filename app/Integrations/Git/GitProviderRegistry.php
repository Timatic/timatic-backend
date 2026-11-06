<?php

declare(strict_types=1);

namespace App\Integrations\Git;

use InvalidArgumentException;

class GitProviderRegistry
{
    /** @var array<string, class-string<GitProviderInterface>> */
    private array $classes = [];

    /** @param class-string<GitProviderInterface> $providerClass */
    public function register(string $type, string $providerClass): void
    {
        $this->classes[$type] = $providerClass;
    }

    /** @param array<string, mixed> $config */
    public function makeProvider(string $type, array $config): GitProviderInterface
    {
        $class = $this->classes[$type]
            ?? throw new InvalidArgumentException("Integration type [{$type}] is not registered.");

        return $class::fromConfig($config);
    }

    /** @return array<string, class-string<GitProviderInterface>> */
    public function all(): array
    {
        return $this->classes;
    }
}
