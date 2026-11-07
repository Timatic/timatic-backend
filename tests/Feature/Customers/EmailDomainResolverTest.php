<?php

use App\Models\Customer;
use App\Services\CustomerEmailDomainResolver;

it('resolves the customer of the first recipient whose domain matches', function () {
    Customer::factory()->create()->emailDomains()->create(['domain' => 'globex.com']);
    $acme = Customer::factory()->create();
    $acme->emailDomains()->create(['domain' => 'acme.com']);

    $customer = app(CustomerEmailDomainResolver::class)->resolve(['jane@unknown.org', 'John@ACME.com', 'hank@globex.com']);

    expect($customer?->id)->toBe($acme->id);
});

it('never resolves a customer from an own organization domain', function () {
    Customer::factory()->create(['is_own_organization' => true])->emailDomains()->create(['domain' => 'pionect.nl']);
    $acme = Customer::factory()->create();
    $acme->emailDomains()->create(['domain' => 'acme.com']);

    $customer = app(CustomerEmailDomainResolver::class)->resolve(['tomas@pionect.nl', 'john@acme.com']);

    expect($customer?->id)->toBe($acme->id);
});

it('resolves no customer when no domain matches', function () {
    Customer::factory()->create()->emailDomains()->create(['domain' => 'acme.com']);

    $customer = app(CustomerEmailDomainResolver::class)->resolve(['jane@app.acme.com', 'john@example.org']);

    expect($customer)->toBeNull();
});
