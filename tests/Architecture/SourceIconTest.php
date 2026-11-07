<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Tests\TestCase;

uses(TestCase::class);

test('suggestion sources publish their icon', function (string $packagePath, string $namespace): void {
    $sourceId = constant("{$namespace}\\ServiceProvider::SOURCE_ID");
    $icon = "{$packagePath}/resources/icons/{$sourceId}.svg";

    $publishedIcons = collect(ServiceProvider::pathsToPublish("{$namespace}\\ServiceProvider", 'source-icons'))
        ->mapWithKeys(fn (string $to, string $from): array => [(string) realpath($from) => $to]);

    expect(app("{$namespace}\\SuggestionProvider")->sourceId())->toBe($sourceId)
        ->and($icon)->toBeFile()
        ->and($publishedIcons->all())->toBe([realpath($icon) => public_path("icons/{$sourceId}.svg")]);
})->with(fn (): array => collect(glob(__DIR__.'/../../integrations/*/composer.json'))
    ->mapWithKeys(fn (string $path): array => [
        basename(dirname($path)) => [
            (string) realpath(dirname($path)),
            rtrim((string) array_key_first(json_decode((string) file_get_contents($path), true)['autoload']['psr-4']), '\\'),
        ],
    ])
    ->filter(fn (array $package): bool => class_exists("{$package[1]}\\SuggestionProvider"))
    ->all());
