<?php

declare(strict_types=1);

use App\Console\Commands\SeedEntrySuggestionsCommand;
use App\DataTransferObjects\BudgetMutation;
use App\DataTransferObjects\EntryExportRow;
use App\Integrations\Exports\ExportProviderInterface;
use App\Integrations\Git\GitProviderInterface;
use App\Integrations\Suggestions\SuggestionProviderInterface;
use App\Integrations\Tickets\TicketProviderInterface;
use App\Mail\BudgetMonthlyBalance;
use App\Mail\BudgetUsageWarning;
use App\Mail\ExportEmail;
use App\Models\DailyProgress;
use App\Models\Period;
use App\Models\TimeSpentTotal;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\Scramble\CustomRelationshipsExtension;
use Filament\Pages\Page;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\ServiceProvider;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Timatic\ExactGlobe\BudgetsExactExport;
use Timatic\ExactGlobe\ExportProvider;
use Timatic\Jira\Requests\GetIssueRequest;

// Pest does not expand wildcards in namespaces, so list each integration package's root namespace
$integrationNamespaces = collect(glob(__DIR__.'/../../integrations/*/composer.json'))
    ->mapWithKeys(fn (string $path): array => [
        dirname($path) => rtrim((string) array_key_first(json_decode((string) file_get_contents($path), true)['autoload']['psr-4']), '\\'),
    ]);

arch()->preset()->php();

// Hashes derive cache keys and resource ids, randomness only fills seed data
arch()->preset()->security()->ignoring([
    'md5',
    'sha1',
    'Database\Seeders',
    SeedEntrySuggestionsCommand::class,
]);

arch()->preset()->laravel()->ignoring([
    AdminPanelProvider::class,
    'Timatic',
    // Sent synchronously on purpose
    BudgetMonthlyBalance::class,
    BudgetUsageWarning::class,
    ExportEmail::class,
    // Computed at runtime, never stored
    DailyProgress::class,
    Period::class,
    TimeSpentTotal::class,
    // API doc tooling that inspects resources by design
    CustomRelationshipsExtension::class,
]);

arch('controllers have no private methods')
    ->expect('App\Http\Controllers')
    ->not->toHavePrivateMethods();

test('controllers are grouped in a folder per resource', function (): void {
    expect(array_map(basename(...), glob(__DIR__.'/../../app/Http/Controllers/*.php')))->toBe(['Controller.php']);
});

arch('app does not depend on integration packages')
    ->expect('App')
    ->not->toUse($integrationNamespaces->values()->all());

// Pest negates an array of namespaces as a whole, so negative rules run per integration
arch('integrations make HTTP calls through Saloon only', function (string $namespace): void {
    expect($namespace)->not->toUse(['Illuminate\Support\Facades\Http', 'GuzzleHttp']);
})->with($integrationNamespaces->values()->all());

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
        // The Exact export reuses the budget mutations of the core monthly export
        ->ignoring([BudgetsExactExport::class, ExportProvider::class]);
})->with($integrationNamespaces->values()->all());

arch('integrations do not use each other', function (string $namespace) use ($integrationNamespaces): void {
    expect($namespace)->not->toUse($integrationNamespaces->reject(fn (string $other): bool => $other === $namespace)->values()->all());
})->with($integrationNamespaces->values()->all());

test('integrations have a service provider', function () use ($integrationNamespaces): void {
    $serviceProviders = $integrationNamespaces->map(fn (string $namespace): string => "{$namespace}\\ServiceProvider");

    expect($serviceProviders->every(fn (string $class): bool => is_subclass_of($class, ServiceProvider::class)))->toBeTrue();
});

arch('integration connectors extend the saloon connector')
    ->expect($integrationNamespaces->map(fn (string $namespace): string => "{$namespace}\\Connector")->filter(fn (string $class): bool => class_exists($class))->values()->all())
    ->toExtend(Connector::class);

arch('integration classes follow the convention of their folder', function (string $folder, ?string $suffix, string $parent) use ($integrationNamespaces): void {
    $namespaces = $integrationNamespaces->map(fn (string $namespace): string => "{$namespace}\\{$folder}")->values()->all();

    if ($suffix !== null) {
        expect($namespaces)->classes()->toHaveSuffix($suffix);
    }

    interface_exists($parent)
        ? expect($namespaces)->classes()->toImplement($parent)
        : expect($namespaces)->classes()->toExtend($parent);
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
    ->expect($integrationNamespaces->map(fn (string $namespace): string => "{$namespace}\\Http\\Controllers")->values()->all())
    ->classes()
    ->toHaveSuffix('Controller');

arch('models do not depend on the HTTP or Filament layer')
    ->expect('App\Models')
    ->not->toUse(['App\Http', 'App\Filament', 'Filament'])
    ->ignoring(User::class);

arch('enums are enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('data transfer objects are readonly')
    ->expect($integrationNamespaces->map(fn (string $namespace): string => "{$namespace}\\DataTransferObjects")->values()->push('App\DataTransferObjects')->all())
    ->toBeReadonly()
    ->ignoring([EntryExportRow::class, BudgetMutation::class]);

// Saloon's base request already defines createDtoFromResponse(), so check each request declares its own
test('saloon requests map their response to a DTO', function () use ($integrationNamespaces): void {
    $requestsWithoutDto = $integrationNamespaces
        ->flatMap(fn (string $namespace, string $packagePath) => collect(glob("{$packagePath}/src/Requests/*.php"))
            ->map(fn (string $file): string => "{$namespace}\\Requests\\".basename($file, '.php')))
        ->reject(fn (string $class): bool => (new ReflectionMethod($class, 'createDtoFromResponse'))->class === $class)
        ->values();

    // GetIssueRequest is mapped in the Jira TicketProvider until #38
    expect($requestsWithoutDto->all())->toBe([GetIssueRequest::class]);
});

arch('capability providers implement their interface', function (string $provider, string $interface) use ($integrationNamespaces): void {
    $providerClasses = $integrationNamespaces
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
