<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use App\Integrations\IntegrationTypeRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Saloon\Config;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function () {
        Config::preventStrayRequests();
    })
    ->in(
        'Feature',
        'Integration',
        'Unit',
        '../integrations/nmbrs/tests',
        '../integrations/exact-globe/tests',
        '../integrations/claude-code/tests'
    );

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<int, string>  $permissions
 */
function loginUser(array $attributes = [], array $permissions = ['user']): Authenticatable
{
    return test()->loginUser($attributes, $permissions);
}

/**
 * The integration packages under /integrations, as package path => root namespace.
 *
 * @return Collection<string, string>
 */
function integrationPackages(): Collection
{
    return collect(glob(__DIR__.'/../integrations/*/composer.json'))
        ->mapWithKeys(fn (string $path): array => [
            (string) realpath(dirname($path)) => rtrim((string) array_key_first(json_decode((string) file_get_contents($path), true)['autoload']['psr-4']), '\\'),
        ]);
}

/**
 * The integration classes that opt out of an architecture rule with an "@arch-ignore <rule>" comment.
 *
 * @return Collection<int, string>
 */
function archIgnored(string $rule): Collection
{
    return integrationPackages()
        ->flatMap(fn (string $namespace, string $path): array => collect(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$path}/src", FilesystemIterator::SKIP_DOTS))))
            ->filter(fn (SplFileInfo $file): bool => str_contains((string) file_get_contents($file->getPathname()), "@arch-ignore {$rule}"))
            ->map(fn (SplFileInfo $file): string => $namespace.'\\'.str_replace('/', '\\', substr($file->getPathname(), strlen("{$path}/src/"), -4)))
            ->values()
            ->all())
        ->values();
}

/**
 * The type an integration registers under: the prefix of its Filament page slugs, or its source id when it has no pages.
 */
function integrationType(string $namespace): string
{
    $slug = collect(app(IntegrationTypeRegistry::class)->allPageEntries())
        ->search(fn (string $page): bool => str_starts_with($page, "{$namespace}\\"));

    return $slug === false
        ? constant("{$namespace}\\ServiceProvider::SOURCE_ID")
        : Str::before($slug, '.');
}
