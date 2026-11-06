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
use Saloon\Http\Request;
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
]);

arch('controllers have no private methods')
    ->expect('App\Http\Controllers')
    ->not->toHavePrivateMethods();

arch('app does not depend on integration packages')
    ->expect('App')
    ->not->toUse('Timatic');

arch('integrations make HTTP calls through Saloon only')
    ->expect(['Illuminate\Support\Facades\Http', 'GuzzleHttp'])
    ->not->toBeUsedIn('Timatic');

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

arch('saloon requests extend the saloon request')
    ->expect($integrationNamespaces->map(fn (string $namespace): string => "{$namespace}\\Requests")->values()->all())
    ->toExtend(Request::class);

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
