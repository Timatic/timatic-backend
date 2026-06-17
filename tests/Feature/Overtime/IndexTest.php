<?php

use App\Models\ApiToken;
use App\Models\Entry;
use App\Models\Overtime;
use App\Models\OvertimeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('returns overtimes of others', function () {
    Event::fake();

    /** @var User $user */
    $user = User::factory()->create();

    $this->loginUser(['user_id' => $user->external_id], ['user', 'overtimes.read']);

    /** @var User $overtimeUser */
    $overtimeUser = User::factory()->create();

    /** @var Overtime $overtime */
    $overtime = Overtime::factory()
        ->create([
            'entry_id' => Entry::factory()->state([
                'user_id' => $overtimeUser->id,
            ]),
            'overtime_type_id' => OvertimeType::PERSONAL,
        ]);

    $this->getJson('overtimes')
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                [
                    'id' => (string) $overtime->id,
                    'type' => 'overtimes',
                ],
            ],
        ]);
});

function it_denies_overtimes_without_read_permissions_to_an_api_token()
{
    /** @var ApiToken $token */
    $token = ApiToken::factory()->create();

    $token->setPermissions(['user']);

    Auth::setUser($token);

    $this->getJson('overtimes')
        ->assertSuccessful();
}
