<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('ends the browser session and sends the user back to the frontend', function () {
    config(['app.frontend_url' => 'https://app.timatic.test']);

    $this->actingAs(User::factory()->create());

    $this->get(route('auth.logout'))
        ->assertRedirect('https://app.timatic.test/login');

    expect(Auth::guard('web')->check())->toBeFalse();
});
