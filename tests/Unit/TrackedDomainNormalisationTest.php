<?php

use App\Models\TrackedDomain;

it('normalises a url to a bare host', function () {
    expect(TrackedDomain::normalise('https://WWW.Acme.com:8443/browse/TIM-1?x=1'))->toEqual('acme.com');
});

it('normalises a url into a domain and a path', function () {
    expect(TrackedDomain::normalise('https://WWW.Jira.Acme.com:8443/projects/TIM/board?x=1'))->toEqual('jira.acme.com');
    expect(TrackedDomain::normalisePath('https://WWW.Jira.Acme.com:8443/projects/TIM/board?x=1'))->toEqual('/projects/TIM/board');
});
