<?php

namespace App\Integrations;

use App\DataTransferObjects\ExportFormat;
use App\DataTransferObjects\ExportPeriod;
use App\Exports\CoreExportProvider;
use App\Integrations\Contracts\ExportInterface;
use App\Integrations\Contracts\ExportProviderInterface;
use App\Models\Integration;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

class ExportService
{
    public function __construct(
        private readonly ExportProviderRegistry $registry,
        private readonly CoreExportProvider $coreProvider,
    ) {}

    /** @return Collection<int, ExportFormat> */
    public function formats(): Collection
    {
        $formats = $this->resolveProviders()
            ->flatMap(fn (ExportProviderInterface $provider) => $provider->exportFormats())
            ->values();

        $duplicateKeys = $formats->countBy(fn (ExportFormat $format) => $format->key)->filter(fn (int $count) => $count > 1);

        if ($duplicateKeys->isNotEmpty()) {
            throw new RuntimeException('Duplicate export format keys registered: '.$duplicateKeys->keys()->implode(', '));
        }

        return $formats;
    }

    public function findFormat(string $key): ?ExportFormat
    {
        return $this->formats()->first(fn (ExportFormat $format) => $format->key === $key);
    }

    /** @return list<string> */
    public function formatKeys(): array
    {
        return array_values($this->formats()->map(fn (ExportFormat $format) => $format->key)->all());
    }

    public function createExport(string $key, ExportPeriod $period): ExportInterface
    {
        $provider = $this->resolveProviders()->first(
            fn (ExportProviderInterface $provider) => $provider->exportFormats()->contains(
                fn (ExportFormat $format) => $format->key === $key,
            ),
        );

        if ($provider === null) {
            throw new InvalidArgumentException("Unknown export format: {$key}");
        }

        return $provider->createExport($key, $period);
    }

    /** @return Collection<int, ExportProviderInterface> */
    private function resolveProviders(): Collection
    {
        return collect([$this->coreProvider])
            ->merge(
                Integration::whereIn('type', $this->registry->registeredTypes())->get()
                    ->flatMap(fn (Integration $integration) => $this->registry->makeProviders(
                        $integration->type,
                        [...($integration->config ?? []), 'integration_id' => $integration->id],
                    )),
            );
    }
}
