<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource as BaseJsonApiResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;

abstract class JsonApiResource extends BaseJsonApiResource
{
    /**
     * @var Collection<string, mixed>|null
     */
    private ?Collection $resolvedCustomRelationships = null;

    /**
     * Relationships the native resource cannot resolve, because they are no Eloquent relations (DTOs, computed periods).
     *
     * @return array<string, Closure(): (BaseJsonApiResource|ResourceCollection|MissingValue|null)>
     */
    public function toCustomRelationships(Request $request): array
    {
        return [];
    }

    /**
     * Only include what the request asks for, not every relation that happens to be eager loaded.
     */
    public function includePreviouslyLoadedRelationships(): static
    {
        return $this;
    }

    /**
     * @return Collection<int, non-empty-array<mixed>>
     */
    public function resolveIncludedResourceObjects(JsonApiRequest $request): Collection
    {
        $customIncluded = $this->resolveCustomRelationships($request)
            ->flatMap(fn (BaseJsonApiResource|Collection|null $related) => $related instanceof BaseJsonApiResource ? [$related] : ($related ?? []))
            ->map(fn (BaseJsonApiResource $related) => $related->resolveResourceData($request));

        // The native resource gives BelongsToMany includes a random key, which duplicates them in `included`
        return parent::resolveIncludedResourceObjects($request)
            ->merge($customIncluded)
            ->map(fn (array $included): array => [...$included, '_uniqueKey' => $included['type'].':'.$included['id']])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveResourceRelationshipIdentifiers(JsonApiRequest $request): array
    {
        $identify = fn (BaseJsonApiResource $related) => [
            'id' => $related->resolveResourceIdentifier($request),
            'type' => $related->resolveResourceType($request),
        ];

        return [
            ...parent::resolveResourceRelationshipIdentifiers($request),
            ...$this->resolveCustomRelationships($request)
                ->map(fn (BaseJsonApiResource|Collection|null $related) => ['data' => match (true) {
                    $related === null => null,
                    $related instanceof BaseJsonApiResource => $identify($related),
                    default => $related->map($identify)->values()->all(),
                }])
                ->all(),
        ];
    }

    /**
     * @return Collection<string, BaseJsonApiResource|Collection<int, BaseJsonApiResource>|null>
     */
    private function resolveCustomRelationships(JsonApiRequest $request): Collection
    {
        $this->resolvedCustomRelationships ??= (new Collection($this->toCustomRelationships($request)))
            ->only($this->requestedResourceRelationships($request))
            ->map(fn (Closure $resolve) => $resolve())
            ->reject(fn (mixed $related) => $related instanceof MissingValue)
            ->map(fn (BaseJsonApiResource|ResourceCollection|null $related) => $related instanceof ResourceCollection ? $related->collection : $related);

        /** @var Collection<string, BaseJsonApiResource|Collection<int, BaseJsonApiResource>|null> $resolved */
        $resolved = $this->resolvedCustomRelationships;

        return $resolved;
    }
}
