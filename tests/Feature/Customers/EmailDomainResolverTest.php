<?php

use App\Models\Customer;
use App\Models\CustomerEmailDomain;
use App\Services\CustomerEmailDomainResolver;

it('resolves the customer of the first recipient whose domain matches', function () {
    CustomerEmailDomain::factory()->create(['domain' => 'globex.com']);
    $acme = CustomerEmailDomain::factory()->create(['domain' => 'acme.com'])->customer;

    $customer = app(CustomerEmailDomainResolver::class)->resolve(['jane@unknown.org', 'John@ACME.com', 'hank@globex.com']);

    expect($customer?->id)->toBe($acme?->id);
});

it('resolves a customer from an address with a display name', function () {
    $acme = CustomerEmailDomain::factory()->create(['domain' => 'acme.com'])->customer;

    $customer = app(CustomerEmailDomainResolver::class)->resolve(['Jane <jane@acme.com>']);

    expect($customer?->id)->toBe($acme?->id);
});

it('never resolves a customer from an own organization domain', function () {
    CustomerEmailDomain::factory()
        ->for(Customer::factory()->state(['is_own_organization' => true]))
        ->create(['domain' => 'pionect.nl']);
    $acme = CustomerEmailDomain::factory()->create(['domain' => 'acme.com'])->customer;

    $customer = app(CustomerEmailDomainResolver::class)->resolve(['tomas@pionect.nl', 'john@acme.com']);

    expect($customer?->id)->toBe($acme?->id);
});

it('resolves no customer when no domain matches', function () {
    CustomerEmailDomain::factory()->create(['domain' => 'acme.com']);

    $customer = app(CustomerEmailDomainResolver::class)->resolve(['jane@app.acme.com', 'john@example.org']);

    expect($customer)->toBeNull();
});
