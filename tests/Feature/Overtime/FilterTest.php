<?php

use App\Models\Entry;
use App\Models\Overtime;
use App\Models\OvertimeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('filters overtimes by exported at column values', function () {
    Event::fake();

    /** @var User $user */
    $user = User::factory()->create();

    $this->loginUser(['user_id' => $user->external_id], ['user', 'overtimes.read']);

    /** @var User $overtimeUser */
    $overtimeUser = User::factory()->create();

    /** @var Overtime $overtimeExported */
    $overtimeExported = Overtime::factory()
        ->create([
            'entry_id' => Entry::factory()->state([
                'user_id' => $overtimeUser->id,
            ]),
            'overtime_type_id' => OvertimeType::PERSONAL,
        ]);

    /** @var Overtime $overtimeNotExported */
    $overtimeNotExported = Overtime::factory()
        ->create([
            'entry_id' => Entry::factory()->state([
                'user_id' => $overtimeUser->id,
            ]),
            'overtime_type_id' => OvertimeType::PERSONAL,
            'exported_at' => null,
        ]);

    $this->getJson('overtimes?filter[isExported]=true')
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                [
                    'id' => (string) $overtimeExported->id,
                    'type' => 'overtimes',
                ],
            ],
        ])
        ->assertJsonMissing([
            'data' => [
                [
                    'id' => (string) $overtimeNotExported->id,
                    'type' => 'overtimes',
                ],
            ],
        ]);
});
