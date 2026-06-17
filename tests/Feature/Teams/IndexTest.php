<?php

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('returns a collection response', function () {
    $this->loginUser(permissions: ['teams.read']);

    $team = Team::factory()->create();

    $this->getJson('teams')
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                ['type' => 'teams'],
            ],
        ]);
});
