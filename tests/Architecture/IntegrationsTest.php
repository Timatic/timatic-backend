<?php

declare(strict_types=1);

use App\Integrations\Exports\ExportProviderInterface;
use App\Integrations\Exports\ExportProviderRegistry;
use App\Integrations\Git\GitProviderInterface;
use App\Integrations\Git\GitProviderRegistry;
use App\Integrations\IntegrationTypeRegistry;
use App\Integrations\Suggestions\SuggestionProviderInterface;
use App\Integrations\Suggestions\SuggestionProviderRegistry;
use App\Integrations\Tickets\TicketProviderInterface;
use App\Integrations\Tickets\TicketProviderRegistry;
use Filament\Pages\Page;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

$packages = integrationPackages();
$namespaces = $packages->values()->all();
$packagesByName = $packages->mapWithKeys(fn (string $namespace, string $path): array => [basename($path) => [$path, $namespace]])->all();

/*
|--------------------------------------------------------------------------
| Boundaries
|--------------------------------------------------------------------------
*/

// Pest negates an array of namespaces as a whole, so negative rules run per integration
arch('integrations make HTTP calls through Saloon only', function (string $namespace): void {
    expect($namespace)->not->toUse(['Illuminate\Support\Facades\Http', 'GuzzleHttp']);
})->with($namespaces);

// Integrations may use the app's models, contracts and data, never its behaviour
$appNamespacesHiddenFromIntegrations = collect(glob(__DIR__.'/../../app/*', GLOB_ONLYDIR))
    ->map(fn (string $path): string => 'App\\'.basename($path))
    ->reject(fn (string $namespace): bool => in_array($namespace, ['App\\Models', 'App\\Integrations', 'App\\DataTransferObjects', 'App\\Enums', 'App\\Filament', 'App\\Http']))
    ->merge(['App\\Http\\Concerns', 'App\\Http\\Middleware', 'App\\Http\\Requests'])
    ->merge(collect(['Controllers', 'Resources'])
        ->flatMap(fn (string $folder): array => glob(__DIR__."/../../app/Http/{$folder}/*"))
        ->map(fn (string $path): string => 'App\\Http\\'.basename(dirname($path)).'\\'.basename($path, '.php'))
        ->reject(fn (string $class): bool => in_array($class, ['App\\Http\\Controllers\\Controller', 'App\\Http\\Resources\\JsonApiResource'])))
    ->values()
    ->all();

arch('integrations only use the app models, contracts and data', function (string $namespace) use ($appNamespacesHiddenFromIntegrations): void {
    expect($namespace)
        ->not->toUse($appNamespacesHiddenFromIntegrations)
        ->ignoring(archIgnored('app-boundary')->all());
})->with($namespaces);

arch('integrations do not use each other', function (string $namespace) use ($namespaces): void {
    expect($namespace)->not->toUse(array_values(array_diff($namespaces, [$namespace])));
})->with($namespaces);

/*
|--------------------------------------------------------------------------
| Package wiring
|--------------------------------------------------------------------------
*/

test('an integration package is wired into composer', function (string $path, string $namespace): void {
    $package = json_decode((string) file_get_contents("{$path}/composer.json"), true);
    $root = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $name = 'timatic/'.basename($path).'-integration';

    expect($package['name'])->toBe($name)
        ->and($package['extra']['laravel']['providers'])->toBe(["{$namespace}\\ServiceProvider"])
        ->and(array_column($root['repositories'], 'url'))->toContain('integrations/'.basename($path))
        ->and($root['require'])->toHaveKey($name);
})->with($packagesByName);

test('an integration declares the composer packages it uses', function (string $path): void {
    $required = array_keys(json_decode((string) file_get_contents("{$path}/composer.json"), true)['require']);
    $source = collect(File::allFiles("{$path}/src"))->map(fn (SplFileInfo $file): string => (string) file_get_contents($file->getPathname()))->implode("\n");

    $missing = collect(['Saloon\\' => 'saloonphp/saloon', 'Firebase\\JWT\\' => 'firebase/php-jwt', 'OpenSpout\\' => 'openspout/openspout'])
        ->filter(fn (string $package, string $namespace): bool => str_contains($source, "use {$namespace}") && ! in_array($package, $required));

    expect($missing->values()->all())->toBe([]);
})->with($packagesByName);

test('integration test folders run in the test suite', function (): void {
    $testFolders = collect(glob(base_path('integrations/*/tests'), GLOB_ONLYDIR))
        ->map(fn (string $path): string => 'integrations/'.basename(dirname($path)).'/tests');
    $phpunit = (string) file_get_contents(base_path('phpunit.xml'));
    $pest = (string) file_get_contents(base_path('tests/Pest.php'));

    expect($testFolders)->not->toBeEmpty();

    foreach ($testFolders as $folder) {
        expect($phpunit)->toContain("./{$folder}")
            ->and($pest)->toContain("'../{$folder}'");
    }
});

test('integrations have a service provider', function (string $path, string $namespace): void {
    expect(is_subclass_of("{$namespace}\\ServiceProvider", ServiceProvider::class))->toBeTrue();
})->with($packagesByName);

/*
|--------------------------------------------------------------------------
| One type string per integration
|--------------------------------------------------------------------------
*/

test('an integration loads its config, views, translations and migrations under its type', function (string $path, string $namespace): void {
    $type = integrationType($namespace);

    foreach (glob("{$path}/config/*.php") as $config) {
        expect(basename($config, '.php'))->toBe($type)
            ->and(config()->has($type))->toBeTrue();
    }

    if (is_dir("{$path}/resources/views")) {
        expect(array_map(realpath(...), app('view')->getFinder()->getHints()[$type] ?? []))->toContain(realpath("{$path}/resources/views"));
    }

    if (is_dir("{$path}/resources/lang")) {
        expect(app('translator')->getLoader()->namespaces())->toHaveKey($type)
            ->and(realpath(app('translator')->getLoader()->namespaces()[$type]))->toBe(realpath("{$path}/resources/lang"));
    }

    if (is_dir("{$path}/database/migrations")) {
        expect(array_map(realpath(...), app('migrator')->paths()))->toContain(realpath("{$path}/database/migrations"));
    }

    if (defined("{$namespace}\\ServiceProvider::SOURCE_ID")) {
        expect(constant("{$namespace}\\ServiceProvider::SOURCE_ID"))->toBe($type);
    }
})->with($packagesByName);

test('the filament pages of an integration are registered under its type', function (string $path, string $namespace): void {
    $type = integrationType($namespace);
    $pages = collect(glob("{$path}/src/Filament/Pages/*.php"))
        ->map(fn (string $file): string => "{$namespace}\\Filament\\Pages\\".basename($file, '.php'));
    $registered = collect(app(IntegrationTypeRegistry::class)->allPageEntries())
        ->filter(fn (string $page): bool => str_starts_with($page, "{$namespace}\\"));

    expect($registered->sort()->values()->all())->toBe($pages->sort()->values()->all());

    foreach ($registered->keys() as $slug) {
        expect($slug)->toStartWith("{$type}.");
    }
})->with($packagesByName);

test('an integration can be renamed and deleted from one of its filament pages', function (string $path): void {
    $pages = collect(glob("{$path}/src/Filament/Pages/*.php"))
        ->map(fn (string $file): string => (string) file_get_contents($file));

    expect($pages->contains(fn (string $page): bool => str_contains($page, "TextInput::make('title')")
        && str_contains($page, "Action::make('save')")
        && str_contains($page, 'DeleteAction::make()')))->toBeTrue();
})->with(array_filter($packagesByName, fn (array $package): bool => glob("{$package[0]}/src/Filament/Pages/*.php") !== []));

test('the commands of an integration are registered with artisan', function (string $path, string $namespace): void {
    $commands = collect(glob("{$path}/src/Commands/*.php"))
        ->map(fn (string $file): string => "{$namespace}\\Commands\\".basename($file, '.php'));
    $registered = array_map(get_class(...), Artisan::all());

    expect($commands->reject(fn (string $command): bool => in_array($command, $registered))->all())->toBe([]);
})->with($packagesByName);

test('the capability providers of an integration are registered under its type', function (string $path, string $namespace): void {
    $type = integrationType($namespace);
    $registered = [
        'TicketProvider' => app(TicketProviderRegistry::class)->all()[$type] ?? null,
        'GitProvider' => app(GitProviderRegistry::class)->all()[$type] ?? null,
        'SuggestionProvider' => app(SuggestionProviderRegistry::class)->all()[$type] ?? null,
        'ExportProvider' => in_array($type, app(ExportProviderRegistry::class)->registeredTypes()) ? "{$namespace}\\ExportProvider" : null,
    ];
    $shipped = collect($registered)->keys()
        ->map(fn (string $provider): string => "{$namespace}\\{$provider}")
        ->filter(fn (string $class): bool => class_exists($class))
        ->reject(fn (string $class): bool => archIgnored('provider-registration')->contains($class));

    expect(array_values(array_filter($registered)))->toBe($shipped->values()->all());
})->with($packagesByName);

test('the tables of an integration are prefixed with its type', function (string $path, string $namespace): void {
    $prefix = str_replace('-', '_', integrationType($namespace)).'_';
    $modelTables = collect(glob("{$path}/src/Models/*.php"))
        ->map(function (string $file) use ($namespace): string {
            /** @var Model $model */
            $model = new ("{$namespace}\\Models\\".basename($file, '.php'));

            return $model->getTable();
        });
    $migrationTables = collect(glob("{$path}/database/migrations/*.php"))
        ->map(fn (string $file): string => (string) file_get_contents($file))
        ->reject(fn (string $migration): bool => str_contains($migration, '@arch-ignore table-prefix'))
        ->flatMap(function (string $migration): array {
            preg_match_all("/Schema::(?:create|table|dropIfExists)\\('(\\w+)'/", $migration, $matches);

            return $matches[1];
        });

    expect($modelTables->merge($migrationTables)->reject(fn (string $table): bool => str_starts_with($table, $prefix))->values()->all())->toBe([]);
})->with($packagesByName);

test('a suggestion source publishes its icon', function (string $path, string $namespace): void {
    $sourceId = constant("{$namespace}\\ServiceProvider::SOURCE_ID");
    $icon = "{$path}/resources/icons/{$sourceId}.svg";

    $publishedIcons = collect(ServiceProvider::pathsToPublish("{$namespace}\\ServiceProvider", 'source-icons'))
        ->mapWithKeys(fn (string $to, string $from): array => [(string) realpath($from) => $to]);

    expect(app("{$namespace}\\SuggestionProvider")->sourceId())->toBe($sourceId)
        ->and($icon)->toBeFile()
        ->and($publishedIcons->all())->toBe([realpath($icon) => public_path("icons/{$sourceId}.svg")]);
})->with(array_filter($packagesByName, fn (array $package): bool => class_exists("{$package[1]}\\SuggestionProvider")));

/*
|--------------------------------------------------------------------------
| Package structure
|--------------------------------------------------------------------------
*/

arch('integration connectors extend the saloon connector')
    ->expect($packages->map(fn (string $namespace): string => "{$namespace}\\Connector")->filter(fn (string $class): bool => class_exists($class))->values()->all())
    ->toExtend(Connector::class);

test('an integration with oauth ships the full token flow', function (string $path, string $namespace): void {
    foreach (['OAuthService', 'OAuthConnector', 'Requests\\ExchangeCodeRequest', 'Requests\\RefreshTokenRequest', 'Http\\Controllers\\CallbackController'] as $class) {
        expect(class_exists("{$namespace}\\{$class}"))->toBeTrue("{$namespace}\\{$class} is missing");
    }
})->with(array_filter($packagesByName, fn (array $package): bool => glob("{$package[0]}/src/OAuth*.php") !== []));

arch('integration classes follow the convention of their folder', function (string $folder, ?string $suffix, string $parent) use ($packages): void {
    $folderNamespaces = $packages->map(fn (string $namespace): string => "{$namespace}\\{$folder}")->values()->all();

    if ($suffix !== null) {
        expect($folderNamespaces)->classes()->toHaveSuffix($suffix);
    }

    interface_exists($parent)
        ? expect($folderNamespaces)->classes()->toImplement($parent)
        : expect($folderNamespaces)->classes()->toExtend($parent);
})->with([
    'requests' => ['Requests', 'Request', Request::class],
    'jobs' => ['Jobs', 'Job', ShouldQueue::class],
    'commands' => ['Commands', 'Command', Command::class],
    'exceptions' => ['Exceptions', 'Exception', Exception::class],
    'filament pages' => ['Filament\\Pages', 'Page', Page::class],
    'mail' => ['Mail', 'Mail', Mailable::class],
    'models' => ['Models', null, Model::class],
]);

arch('integration controllers are suffixed')
    ->expect($packages->map(fn (string $namespace): string => "{$namespace}\\Http\\Controllers")->values()->all())
    ->classes()
    ->toHaveSuffix('Controller');

arch('integration data transfer objects are readonly')
    ->expect($packages->map(fn (string $namespace): string => "{$namespace}\\DataTransferObjects")->values()->all())
    ->toBeReadonly();

// Saloon's base request already defines createDtoFromResponse(), so check each request declares its own
test('saloon requests map their response to a DTO', function () use ($packages): void {
    $requestsWithoutDto = $packages
        ->flatMap(fn (string $namespace, string $path) => collect(glob("{$path}/src/Requests/*.php"))
            ->map(fn (string $file): string => "{$namespace}\\Requests\\".basename($file, '.php')))
        ->reject(fn (string $class): bool => (new ReflectionMethod($class, 'createDtoFromResponse'))->class === $class)
        ->reject(fn (string $class): bool => archIgnored('response-dto')->contains($class))
        ->values();

    expect($requestsWithoutDto->all())->toBe([]);
});

arch('capability providers implement their interface', function (string $provider, string $interface) use ($packages): void {
    $providerClasses = $packages
        ->map(fn (string $namespace): string => "{$namespace}\\{$provider}")
        ->filter(fn (string $class): bool => class_exists($class));

    expect($providerClasses)->not->toBeEmpty();
    expect($providerClasses->all())->toImplement($interface);
})->with([
    'tickets' => ['TicketProvider', TicketProviderInterface::class],
    'git' => ['GitProvider', GitProviderInterface::class],
    'exports' => ['ExportProvider', ExportProviderInterface::class],
    'suggestions' => ['SuggestionProvider', SuggestionProviderInterface::class],
]);
