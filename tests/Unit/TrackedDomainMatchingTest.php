<?php

use App\Models\TrackedDomain;

it('normalises a url to a bare host', function () {
    expect(TrackedDomain::normalise('https://WWW.Acme.com:8443/browse/TIM-1?x=1'))->toEqual('acme.com');
});

it('matches a subdomain against a mapping on the parent domain', function () {
    $trackedDomain = TrackedDomain::factory()->create(['domain' => 'acme.com']);

    expect(TrackedDomain::matching('https://app.acme.com/dashboard')?->id)->toEqual($trackedDomain->id);
});

it('does not match a host that merely ends with the mapped domain', function () {
    TrackedDomain::factory()->create(['domain' => 'acme.com']);

    expect(TrackedDomain::matching('notacme.com'))->toBeNull();
});

it('prefers the most specific mapping', function () {
    TrackedDomain::factory()->create(['domain' => 'acme.com']);
    $specific = TrackedDomain::factory()->create(['domain' => 'jira.acme.com']);

    expect(TrackedDomain::matching('jira.acme.com')?->id)->toEqual($specific->id);
});

it('ignores an inactive mapping', function () {
    TrackedDomain::factory()->create(['domain' => 'acme.com', 'is_active' => false]);

    expect(TrackedDomain::matching('acme.com'))->toBeNull();
});
