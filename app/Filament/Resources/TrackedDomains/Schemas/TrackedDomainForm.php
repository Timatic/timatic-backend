<?php

namespace App\Filament\Resources\TrackedDomains\Schemas;

use App\Models\Budget;
use App\Models\Customer;
use App\Models\TrackedDomain;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TrackedDomainForm
{
    public static function configure(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('domain')
                ->required()
                ->helperText('Subdomains are covered too: acme.com also tracks app.acme.com.')
                ->dehydrateStateUsing(fn (string $state): string => TrackedDomain::normalise($state)),
            TextInput::make('path')
                ->helperText('Optional. /projects/TIM only tracks that part of the domain, and wins over a mapping on the whole domain.')
                ->dehydrateStateUsing(fn (?string $state): string => TrackedDomain::normalisePath((string) $state)),
            Select::make('customer_id')
                ->label('Customer')
                ->options(Customer::pluck('name', 'id'))
                ->searchable()
                ->required()
                ->live(),
            Select::make('budget_id')
                ->label('Budget')
                ->helperText('Leave empty to book the time as paid per hour.')
                ->options(fn (Get $get): array => Budget::query()
                    ->where('customer_id', $get('customer_id'))
                    ->get()
                    ->mapWithKeys(fn (Budget $budget): array => [$budget->id => $budget->getTitle()])
                    ->all())
                ->searchable(),
            Select::make('user_id')
                ->label('Private to')
                ->helperText('Leave empty to share the mapping with everyone. Used for local development domains, which differ per engineer.')
                ->options(User::pluck('email', 'id'))
                ->searchable(),
            Toggle::make('is_internal')->label('Internal'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }
}
