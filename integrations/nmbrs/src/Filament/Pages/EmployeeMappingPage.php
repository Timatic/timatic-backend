<?php

namespace Timatic\Nmbrs\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Timatic\Nmbrs\Connector;
use Timatic\Nmbrs\DataTransferObjects\NmbrsEmployee;
use Timatic\Nmbrs\OAuthService;
use Timatic\Nmbrs\Services\NmbrsEmployeeService;

/**
 * @property Table $table
 */
class EmployeeMappingPage extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'nmbrs::filament.pages.employee-mapping-page';

    /** @var array<int, array{id: string, nmbrs_employee_number: string|null, nmbrs_email: string|null, timatic_user_name: string|null, timatic_user_email: string|null, is_mapped: bool}> */
    public array $employees = [];

    public function getTitle(): string
    {
        return $this->getIntegration()->name.' — Medewerkers';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if ($this->isConnected()) {
            $this->loadEmployees();
        }
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Start;
    }

    public function getSubNavigation(): array
    {
        $record = $this->getRecord();

        return [
            NavigationItem::make('Instellingen')
                ->url(SettingsPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === SettingsPage::getUrl(['record' => $record])),
            NavigationItem::make('Medewerkers')
                ->url(EmployeeMappingPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === EmployeeMappingPage::getUrl(['record' => $record])),
            NavigationItem::make('Overwerk synchronisatie')
                ->url(OvertimeSyncPage::getUrl(['record' => $record]))
                ->isActiveWhen(fn () => request()->url() === OvertimeSyncPage::getUrl(['record' => $record])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->employees)
            ->columns([
                IconColumn::make('is_mapped')
                    ->label('Gekoppeld')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('nmbrs_employee_number')
                    ->label('Pers. Nr.'),
                TextColumn::make('nmbrs_email')
                    ->label('E-mail NMBRS'),
                TextColumn::make('timatic_user_name')
                    ->label('Timatic gebruiker')
                    ->placeholder('—'),
                TextColumn::make('timatic_user_email')
                    ->label('E-mail Timatic')
                    ->placeholder('—'),
            ])
            ->heading('Medewerkers')
            ->emptyStateHeading('Geen medewerkers gevonden')
            ->emptyStateDescription('Klik op "Vernieuwen" om medewerkers op te halen uit NMBRS.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Vernieuwen')
                ->action(function (): void {
                    Cache::forget($this->nmbrsEmployeesCacheKey());
                    $this->loadEmployees();
                    $this->resetTable();
                }),
        ];
    }

    private function loadEmployees(): void
    {
        /** @var array<string, array{employee_id: string, employee_number: string|null, email: string|null}> $nmbrsEmployees */
        $nmbrsEmployees = Cache::remember(
            $this->nmbrsEmployeesCacheKey(),
            now()->addHour(),
            fn () => $this->fetchNmbrsEmployees(),
        );

        /** @var Collection<string, User> $allUsersByEmail */
        $allUsersByEmail = User::all()->keyBy(fn (User $user) => strtolower((string) $user->email));

        $nmbrsEmailsLower = array_values(array_filter(array_column($nmbrsEmployees, 'email')));

        $rows = collect($nmbrsEmployees)
            ->map(function (array $nmbrsEmployee) use ($allUsersByEmail): array {
                $user = $nmbrsEmployee['email'] ? $allUsersByEmail->get($nmbrsEmployee['email']) : null;

                return [
                    'id' => 'nmbrs_'.$nmbrsEmployee['employee_id'],
                    'nmbrs_employee_number' => $nmbrsEmployee['employee_number'],
                    'nmbrs_email' => $nmbrsEmployee['email'],
                    'timatic_user_name' => $user?->full_name,
                    'timatic_user_email' => $user?->email,
                    'is_mapped' => $user !== null,
                ];
            });

        $unmatchedTimaticUsers = $allUsersByEmail
            ->filter(fn (User $user) => ! in_array(strtolower((string) $user->email), $nmbrsEmailsLower))
            ->map(fn (User $user): array => [
                'id' => 'timatic_'.$user->id,
                'nmbrs_employee_number' => null,
                'nmbrs_email' => null,
                'timatic_user_name' => $user->full_name,
                'timatic_user_email' => $user->email,
                'is_mapped' => false,
            ]);

        $this->employees = $rows->merge($unmatchedTimaticUsers)->values()->all();
    }

    /** @return array<string, array{employee_id: string, employee_number: string|null, email: string|null}> */
    private function fetchNmbrsEmployees(): array
    {
        $integration = app(OAuthService::class)->refreshIfExpired($this->getIntegration());
        $config = $integration->config ?? [];
        $companyId = $config['company_id'] ?? null;

        if (empty($companyId)) {
            return [];
        }

        $connector = new Connector($config['access_token']);
        $employeeService = new NmbrsEmployeeService($connector, $companyId);

        return $employeeService->listByEmail()
            ->mapWithKeys(fn (NmbrsEmployee $employee): array => [
                $employee->employeeId => [
                    'employee_id' => $employee->employeeId,
                    'employee_number' => $employee->employeeNumber,
                    'email' => $employee->businessEmail,
                ],
            ])
            ->all();
    }

    private function nmbrsEmployeesCacheKey(): string
    {
        return 'nmbrs_employees_'.$this->getRecord()->getKey();
    }

    private function isConnected(): bool
    {
        $config = $this->getIntegration()->config ?? [];

        return filled($config['access_token'] ?? null) && filled($config['company_id'] ?? null);
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
