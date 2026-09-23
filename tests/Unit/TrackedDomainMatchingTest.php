<?php

use App\Models\TrackedDomain;
use App\Models\User;

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

it('normalises a url into a domain and a path', function () {
    expect(TrackedDomain::normalise('https://WWW.Jira.Acme.com:8443/projects/TIM/board?x=1'))->toEqual('jira.acme.com');
    expect(TrackedDomain::normalisePath('https://WWW.Jira.Acme.com:8443/projects/TIM/board?x=1'))->toEqual('/projects/TIM/board');
});

it('matches a mapping on a path', function () {
    $trackedDomain = TrackedDomain::factory()->create(['domain' => 'gitlab.acme.com', 'path' => '/group-a']);

    expect(TrackedDomain::matching('https://gitlab.acme.com/group-a/project/-/issues/3')?->id)->toEqual($trackedDomain->id);
});

it('does not match a url outside the mapped path', function () {
    TrackedDomain::factory()->create(['domain' => 'gitlab.acme.com', 'path' => '/group-a']);

    expect(TrackedDomain::matching('https://gitlab.acme.com/group-b/project'))->toBeNull();
});

it('does not match a path that merely starts with the mapped path', function () {
    TrackedDomain::factory()->create(['domain' => 'gitlab.acme.com', 'path' => '/group-a']);

    expect(TrackedDomain::matching('https://gitlab.acme.com/group-alpha'))->toBeNull();
});

it('prefers the mapping with the longest path', function () {
    TrackedDomain::factory()->create(['domain' => 'gitlab.acme.com']);
    $specific = TrackedDomain::factory()->create(['domain' => 'gitlab.acme.com', 'path' => '/group-a/project']);

    expect(TrackedDomain::matching('https://gitlab.acme.com/group-a/project/-/issues/3')?->id)->toEqual($specific->id);
});

it('falls back to the mapping on the whole domain', function () {
    $whole = TrackedDomain::factory()->create(['domain' => 'gitlab.acme.com']);
    TrackedDomain::factory()->create(['domain' => 'gitlab.acme.com', 'path' => '/group-a']);

    expect(TrackedDomain::matching('https://gitlab.acme.com/group-b')?->id)->toEqual($whole->id);
});

it('prefers a user their own mapping over the shared one', function () {
    $user = User::factory()->create();

    TrackedDomain::factory()->create(['domain' => 'acme.com']);
    $own = TrackedDomain::factory()->create(['domain' => 'acme.com', 'user_id' => $user->id]);

    expect(TrackedDomain::matching('acme.com', $user->id)?->id)->toEqual($own->id);
});

it('does not match a private mapping of somebody else', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    TrackedDomain::factory()->create(['domain' => 'timatic-backend.test', 'user_id' => $owner->id]);

    expect(TrackedDomain::matching('timatic-backend.test', $other->id))->toBeNull();
    expect(TrackedDomain::matching('timatic-backend.test', $owner->id))->not->toBeNull();
});
