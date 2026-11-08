<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use App\Models\CustomerEmailDomain;
use App\Models\User;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('external_id')->label('External ID')->disabled(),
            TextInput::make('name')->required(),
            TextInput::make('hourly_rate')->numeric()->prefix('€'),
            Select::make('account_manager_user_id')
                ->label('Account manager')
                ->options(User::pluck('email', 'id'))
                ->searchable(),
            Toggle::make('is_own_organization')
                ->label('Own organization')
                ->helperText(__('customers.own_organization.helper'))
                ->live(),
            TagsInput::make('email_domains')
                ->label(__('customers.email_domains.label'))
                ->helperText(fn (Get $get): string => $get('is_own_organization')
                    ? __('customers.email_domains.helper_own')
                    : __('customers.email_domains.helper'))
                ->placeholder('acme.com')
                ->splitKeys([',', ';', ' '])
                ->loadStateFromRelationshipsUsing(fn (TagsInput $component, Customer $record) => $component->state(
                    $record->emailDomains()->orderBy('domain')->pluck('domain')->all()
                ))
                ->nestedRecursiveRules([
                    fn (?Customer $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $domain = CustomerEmailDomain::normalise((string) $value);

                        if (! CustomerEmailDomain::isValid($domain)) {
                            $fail(__('customers.email_domains.invalid', ['domain' => $value]));

                            return;
                        }

                        $holder = CustomerEmailDomain::query()
                            ->where('domain', $domain)
                            ->when($record?->id !== null, fn ($query) => $query->where('customer_id', '!=', $record?->id))
                            ->first()
                            ?->customer;

                        if ($holder !== null) {
                            $fail(__('customers.email_domains.taken', ['domain' => $domain, 'customer' => $holder->name]));
                        }
                    },
                ])
                ->dehydrated(false)
                ->saveRelationshipsUsing(function (Customer $record, ?array $state): void {
                    $domains = collect($state ?? [])->map(fn (string $domain) => CustomerEmailDomain::normalise($domain))->unique();

                    $record->emailDomains()->whereNotIn('domain', $domains)->delete();
                    $domains->each(fn (string $domain) => $record->emailDomains()->firstOrCreate(['domain' => $domain]));
                }),
        ]);
    }
}
