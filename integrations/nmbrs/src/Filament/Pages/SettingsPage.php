<?php

namespace Timatic\Nmbrs\Filament\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use App\Models\OvertimeRule;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Timatic\Nmbrs\Connector;
use Timatic\Nmbrs\DataTransferObjects\NmbrsCompany;
use Timatic\Nmbrs\DataTransferObjects\NmbrsHourCode;
use Timatic\Nmbrs\OAuthService;
use Timatic\Nmbrs\Requests\GetCompaniesRequest;
use Timatic\Nmbrs\Requests\GetHourCodesRequest;

/**
 * @property Schema $form
 */
class SettingsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = IntegrationResource::class;

    protected string $view = 'nmbrs::filament.pages.settings-page';

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var Collection<int, NmbrsHourCode>|null */
    private ?Collection $hourCodes = null;

    /** @var Collection<int, NmbrsCompany>|null */
    private ?Collection $companies = null;

    public function getTitle(): string
    {
        return $this->getIntegration()->name;
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

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $config = $this->getIntegration()->config ?? [];
        $hourCodes = $config['hour_codes'] ?? [];

        $this->form->fill([
            'name' => $this->getIntegration()->name,
            'company_id' => $config['company_id'] ?? null,
            'management_emails' => $config['management_emails'] ?? '',
            ...$this->hourCodeFormData('fulltime', $hourCodes['fulltime'] ?? []),
            ...$this->hourCodeFormData('parttime', $hourCodes['parttime'] ?? []),
            'sync_leave_enabled' => $config['sync_leave_enabled'] ?? true,
            'sync_overtime_enabled' => $config['sync_overtime_enabled'] ?? false,
            'overtime_sync_day' => $config['overtime_sync_day'] ?? null,
            'overtime_sync_time' => $config['overtime_sync_time'] ?? null,
        ]);

        if (session('nmbrs_success')) {
            Notification::make()->title(session('nmbrs_success'))->success()->send();
        }

        if (session('nmbrs_error')) {
            Notification::make()->title(session('nmbrs_error'))->danger()->send();
        }
    }

    public function form(Schema $form): Schema
    {
        if (! $this->isConfigured()) {
            return $form->schema([
                Callout::make('NMBRS credentials zijn niet geconfigureerd.')
                    ->danger()
                    ->description('Voeg NMBRS_CLIENT_ID en NMBRS_CLIENT_SECRET toe aan je .env bestand.'),
            ])->statePath('data');
        }

        if (! $this->isConnected()) {
            return $form->schema([
                Callout::make('Niet verbonden met NMBRS.')
                    ->warning()
                    ->description('Klik op "Verbinden" om de OAuth koppeling in te stellen.'),
            ])->statePath('data');
        }

        return $form->schema([
            TextInput::make('name')
                ->label('Naam integratie')
                ->required(),

            Select::make('company_id')
                ->label('Bedrijf')
                ->options($this->loadCompanyOptions())
                ->required()
                ->searchable(),

            Textarea::make('management_emails')
                ->label('Management e-mailadressen')
                ->helperText('Eén e-mailadres per regel.')
                ->rows(3),

            Section::make('Verlof synchronisatie')
                ->schema([
                    Toggle::make('sync_leave_enabled')
                        ->label('Verlof automatisch synchroniseren')
                        ->helperText('Dagelijks om 07:00 worden goedgekeurde verlofaanvragen als entries aangemaakt.')
                        ->default(true),
                ]),

            Section::make('Overwerk synchronisatie')
                ->schema([
                    Toggle::make('sync_overtime_enabled')
                        ->label('Overwerk automatisch synchroniseren naar NMBRS')
                        ->default(false)
                        ->live(),

                    Select::make('overtime_sync_day')
                        ->label('Dag van de maand')
                        ->options(array_map(fn (int $n): string => (string) $n, array_combine(range(1, 28), range(1, 28))))
                        ->visible(fn ($get) => $get('sync_overtime_enabled'))
                        ->required(fn ($get) => $get('sync_overtime_enabled')),

                    TextInput::make('overtime_sync_time')
                        ->label('Tijdstip (HH:MM)')
                        ->placeholder('06:00')
                        ->visible(fn ($get) => $get('sync_overtime_enabled'))
                        ->required(fn ($get) => $get('sync_overtime_enabled')),

                    Section::make('Uurcodes fulltime (40u/week)')
                        ->schema($this->hourCodeSelects('fulltime'))
                        ->columns(3)
                        ->visible(fn ($get) => $get('sync_overtime_enabled')),

                    Section::make('Uurcodes parttime (<40u/week)')
                        ->schema($this->hourCodeSelects('parttime'))
                        ->columns(3)
                        ->visible(fn ($get) => $get('sync_overtime_enabled')),
                ]),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('disconnect')
                ->label('Verbreken')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    app(OAuthService::class)->disconnect($this->getIntegration());
                    $this->redirect(request()->url());
                })
                ->visible($this->isConfigured() && $this->isConnected()),

            Action::make('connect')
                ->label('Verbinden met NMBRS')
                ->url(route('nmbrs.oauth.redirect', $this->getRecord()))
                ->visible($this->isConfigured() && ! $this->isConnected()),

            Action::make('save')
                ->label('Opslaan')
                ->action(function (): void {
                    $data = $this->form->getState();

                    $overtimeRules = OvertimeRule::query()->pluck('percentage');

                    $companyId = $data['company_id'];
                    $companyName = $this->loadCompanyOptions()[$companyId] ?? '';

                    $config = array_merge($this->getIntegration()->config ?? [], [
                        'company_id' => $companyId,
                        'company_name' => $companyName,
                        'management_emails' => $data['management_emails'] ?? '',
                        'hour_codes' => [
                            'fulltime' => $overtimeRules
                                ->mapWithKeys(fn (string $percentage): array => [
                                    (int) $percentage => (int) ($data["hour_code_fulltime_{$percentage}"] ?? 0),
                                ]),
                            'parttime' => $overtimeRules
                                ->mapWithKeys(fn (string $percentage): array => [
                                    (int) $percentage => (int) ($data["hour_code_parttime_{$percentage}"] ?? 0),
                                ]),
                        ],
                        'sync_leave_enabled' => (bool) ($data['sync_leave_enabled'] ?? true),
                        'sync_overtime_enabled' => (bool) ($data['sync_overtime_enabled'] ?? false),
                        'overtime_sync_day' => $data['overtime_sync_day'] ?? null,
                        'overtime_sync_time' => $data['overtime_sync_time'] ?? null,
                    ]);

                    $this->getIntegration()->update([
                        'name' => $data['name'],
                        'config' => $config,
                    ]);

                    Notification::make()->title('Instellingen opgeslagen.')->success()->send();
                })
                ->visible($this->isConnected()),
        ];
    }

    /** @param array<int, int> $existing
     * @return array<string, int|null> */
    private function hourCodeFormData(string $type, array $existing): array
    {
        return OvertimeRule::query()
            ->distinct()
            ->orderBy('percentage')
            ->pluck('percentage')
            ->mapWithKeys(fn (string $percentage): array => [
                "hour_code_{$type}_{$percentage}" => isset($existing[(int) $percentage]) ? (int) $existing[(int) $percentage] : null,
            ])
            ->all();
    }

    /** @return array<int, Select> */
    private function hourCodeSelects(string $type): array
    {
        $hourCodes = $this->loadHourCodeOptions();

        return OvertimeRule::query()
            ->distinct()
            ->orderBy('percentage')
            ->pluck('percentage')
            ->map(fn (string $percentage): Select => Select::make("hour_code_{$type}_{$percentage}")
                ->label("{$percentage}% overwerk")
                ->options(($hourCodes ?? collect())
                    ->mapWithKeys(fn (NmbrsHourCode $hourCode): array => [$hourCode->code => $hourCode->label()])
                )
                ->required()
            )
            ->all();
    }

    /** @return array<string, string> */
    private function loadCompanyOptions(): array
    {
        if (isset($this->companies)) {
            return $this->companies
                ->mapWithKeys(fn (NmbrsCompany $company): array => [$company->companyId => $company->name])
                ->all();
        }

        $integration = app(OAuthService::class)->refreshIfExpired($this->getIntegration());
        $connector = new Connector($integration->config['access_token']);
        $response = $connector->send(new GetCompaniesRequest);

        if ($response->failed()) {
            Notification::make()->title('Kon bedrijven niet ophalen uit NMBRS.')->warning()->send();

            return [];
        }

        $this->companies = $response->dto();

        if ($this->companies === null) {
            return [];
        }

        return $this->companies
            ->mapWithKeys(fn (NmbrsCompany $company): array => [$company->companyId => $company->name])
            ->all();
    }

    /**
     * @return Collection<int, NmbrsHourCode>|null
     */
    private function loadHourCodeOptions(): ?Collection
    {
        if (isset($this->hourCodes)) {
            return $this->hourCodes;
        }

        $integration = app(OAuthService::class)->refreshIfExpired($this->getIntegration());
        $connector = new Connector($integration->config['access_token']);
        $response = $connector->send(new GetHourCodesRequest($integration->config['company_id']));

        if ($response->failed()) {
            Notification::make()->title('Kon uurcodes niet ophalen uit NMBRS.')->warning()->send();

            return null;
        }

        $this->hourCodes = $response->dto();

        return $this->hourCodes;
    }

    private function isConfigured(): bool
    {
        return filled(config('nmbrs.client_id')) && filled(config('nmbrs.client_secret'));
    }

    private function isConnected(): bool
    {
        $config = $this->getIntegration()->config ?? [];

        return $this->isConfigured() && filled($config['access_token'] ?? null) && filled($config['company_id'] ?? null);
    }

    private function getIntegration(): Integration
    {
        /** @var Integration */
        return Integration::findOrFail($this->getRecord()->getKey());
    }
}
