<?php

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Models\Customer;
use App\Models\CustomerEmailDomain;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super_admin'));
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
});

it('saves the email domains of a customer normalised', function () {
    $customer = CustomerEmailDomain::factory()->create(['domain' => 'old.example'])->customer;

    Livewire::test(EditCustomer::class, ['record' => $customer->id])
        ->assertFormSet(['email_domains' => ['old.example']])
        ->fillForm(['email_domains' => ['@Acme.COM', 'acme.nl']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($customer->emailDomains()->orderBy('domain')->pluck('domain')->all())->toBe(['acme.com', 'acme.nl']);
});

it('rejects an email domain that is not a hostname', function () {
    $customer = Customer::factory()->create();

    Livewire::test(EditCustomer::class, ['record' => $customer->id])
        ->fillForm(['email_domains' => ['not a domain']])
        ->call('save')
        ->assertHasFormErrors(['email_domains.0']);

    expect(CustomerEmailDomain::query()->count())->toBe(0);
});

it('rejects an email domain of another customer', function () {
    $customer = Customer::factory()->create();
    CustomerEmailDomain::factory()
        ->for(Customer::factory()->state(['name' => 'Acme']))
        ->create(['domain' => 'acme.com']);

    Livewire::test(EditCustomer::class, ['record' => $customer->id])
        ->fillForm(['email_domains' => ['@ACME.com']])
        ->call('save')
        ->assertHasFormErrors(['email_domains.0' => __('customers.email_domains.taken', ['domain' => 'acme.com', 'customer' => 'Acme'])]);

    expect($customer->emailDomains()->count())->toBe(0);
});

it('releases the email domains of a deleted customer', function () {
    $customer = Customer::factory()->create();
    CustomerEmailDomain::factory()->create(['domain' => 'acme.com'])->customer?->delete();

    Livewire::test(EditCustomer::class, ['record' => $customer->id])
        ->fillForm(['email_domains' => ['acme.com']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($customer->emailDomains()->pluck('domain')->all())->toBe(['acme.com']);
});
