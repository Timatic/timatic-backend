<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('links a google login to a user that was added with only an email address', function () {
    // Given
    User::factory()->create();
    $user = User::factory()->create([
        'email' => 'jan@example.com',
        'external_id' => null,
        'given_name' => null,
        'family_name' => null,
    ]);

    $socialiteUser = (new SocialiteUser)->setRaw([
        'given_name' => 'Jan',
        'family_name' => 'van der Berg',
    ])->map([
        'id' => 'google-123',
        'email' => 'jan@example.com',
        'name' => 'Jan van der Berg',
    ]);
    Socialite::shouldReceive('driver->stateless->user')->andReturn($socialiteUser);

    // When
    $this->get(route('auth.callback'));

    // Then
    $this->assertAuthenticatedAs($user, 'web');
    expect(User::count())->toBe(2)
        ->and($user->refresh())
        ->external_id->toBe('google-123')
        ->given_name->toBe('Jan')
        ->family_name->toBe('van der Berg')
        ->and($user->hasRole('employee'))->toBeTrue();
});
