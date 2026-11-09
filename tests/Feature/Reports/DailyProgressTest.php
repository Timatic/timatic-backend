<?php

use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('measures progress in clock time, not minutes spent', function () {
    $user = $this->loginUser();

    Entry::factory()->create([
        'user_id' => $user->id,
        'started_at' => Carbon::create(2026, 3, 10, 9, timezone: 'Europe/Amsterdam')->utc(),
        'ended_at' => Carbon::create(2026, 3, 10, 11, timezone: 'Europe/Amsterdam')->utc(),
        'minutes_spent' => 180,
    ]);

    $this->getJson('daily-progress?filter[date]=2026-03-10')
        ->assertSuccessful()
        ->assertJsonPath('data.0.attributes.date', '2026-03-10')
        ->assertJsonPath('data.0.attributes.progress', 25);
});
