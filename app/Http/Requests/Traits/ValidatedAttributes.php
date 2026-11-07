<?php

declare(strict_types=1);

namespace App\Http\Requests\Traits;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait ValidatedAttributes
{
    /**
     * @return array<string>
     */
    public function validatedAttributes(bool $convertToSnakeCase = true): array
    {
        $attributes = $this->validated()['data']['attributes'];

        if (! $convertToSnakeCase) {
            return $attributes;
        }

        $keys = array_map(fn (int|string $key) => Str::snake((string) $key), array_keys($attributes));

        return array_combine($keys, array_values($attributes));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function relationships(string $key): ?array
    {
        return $this->validated('data.relationships.'.$key) ?? null;
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    public function matchesRouteResourceId(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $routeResource = Arr::first($this->route()?->parameters() ?? []);
            $routeResourceId = $routeResource instanceof Model ? $routeResource->getKey() : $routeResource;

            if ($value !== (string) $routeResourceId) {
                $fail('The id does not match the resource in the URL.');
            }
        };
    }
}
