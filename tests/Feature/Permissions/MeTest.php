<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('returns a locked date permission of previous month before month end with permission to update from previous month', function () {
    $preferredTimezone = config('timatic.preferred_timezone');

    $this->travelTo(Carbon::now($preferredTimezone)->firstOfMonth());

    /** @var User $user */
    $user = $this->loginUser(permissions: ['user', 'entries.update_from_previous_month']);

    $included = (new Collection(
        $this->getJson('/me?include=permissions')
            ->assertSuccessful()
            ->json('included')
    ))->where('type', 'permissions');

    $derivedPermission = null;

    foreach ($included as $permission) {
        if (
            isset($permission['attributes']['values'])
            && isset($permission['attributes']['values']['lockedAt'])
        ) {
            $derivedPermission = $permission;
        }
    }

    expect(Carbon::parse(
        $derivedPermission['attributes']['values']['lockedAt'],
        $preferredTimezone
    ))->toEqual(Carbon::now($preferredTimezone)->firstOfMonth()->subMonth()->subSecond());
});

it('returns a locked date permission of entries locked after days', function () {
    $preferredTimezone = config('timatic.preferred_timezone');

    $lockedAfterDays = config('timatic.entries_locked_after_days');

    $this->travelTo(Carbon::now($preferredTimezone)->firstOfMonth());

    /** @var User $user */
    $user = $this->loginUser();

    $included = (new Collection(
        $this->getJson('/me?include=permissions')
            ->assertSuccessful()
            ->json('included')
    ))->where('type', 'permissions');

    $derivedPermission = null;

    foreach ($included as $permission) {
        if (
            isset($permission['attributes']['values'])
            && isset($permission['attributes']['values']['lockedAt'])
        ) {
            $derivedPermission = $permission;
        }
    }

    expect(Carbon::parse(
        $derivedPermission['attributes']['values']['lockedAt']
    ))->toEqual(Carbon::now($preferredTimezone)->subDays($lockedAfterDays)->subSecond());
});

it('returns a locked date with extended closing date with permission to edit previous month', function () {
    $preferredTimezone = config('timatic.preferred_timezone');

    $lockedAfterDays = config('timatic.extended_closing_day_of_month');

    $this->travelTo(Carbon::now($preferredTimezone)->day(13));

    /** @var User $user */
    $user = $this->loginUser(permissions: ['entries.update_from_previous_month']);

    $included = (new Collection(
        $this->getJson('/me?include=permissions')
            ->assertSuccessful()
            ->json('included')
    ))->where('type', 'permissions');

    $derivedPermission = null;

    foreach ($included as $permission) {
        if (isset($permission['attributes']['values']['lockedAt'])) {
            $derivedPermission = $permission;
        }
    }

    expect(Carbon::parse(
        $derivedPermission['attributes']['values']['lockedAt']
    ))->toEqual(Carbon::now($preferredTimezone)->subMonth()->firstOfMonth()->subSecond());
});
