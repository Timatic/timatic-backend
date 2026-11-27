<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('returns the default hourly rate', function () {
    $this->loginUser();
    config(['timatic.default_hourly_rate' => '85']);

    $this->getJson('settings')
        ->assertSuccessful()
        ->assertJsonPath('data.attributes.defaultHourlyRate', 85);
});
