<?php

declare(strict_types=1);

use App\Console\Commands\SeedEntrySuggestionsCommand;
use App\DataTransferObjects\BudgetMutation;
use App\DataTransferObjects\EntryExportRow;
use App\Http\Resources\JsonApiResource;
use App\Mail\BudgetMonthlyBalance;
use App\Mail\BudgetUsageWarning;
use App\Mail\ExportEmail;
use App\Models\DailyProgress;
use App\Models\Period;
use App\Models\TimeSpentTotal;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\Scramble\CustomRelationshipsExtension;

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
    ->not->toUse(integrationPackages()->values()->all());

arch('models do not depend on the HTTP or Filament layer')
    ->expect('App\Models')
    ->not->toUse(['App\Http', 'App\Filament', 'Filament'])
    ->ignoring(User::class);

arch('api resources extend the json api resource')
    ->expect('App\Http\Resources')
    ->classes()
    ->toExtend(JsonApiResource::class)
    ->ignoring(JsonApiResource::class);

arch('enums are enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('data transfer objects are readonly')
    ->expect('App\DataTransferObjects')
    ->toBeReadonly()
    ->ignoring([EntryExportRow::class, BudgetMutation::class]);
