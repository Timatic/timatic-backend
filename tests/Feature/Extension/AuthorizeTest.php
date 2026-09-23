<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

beforeEach(function () {
    config(['extension.ids' => ['abcdefghijklmnopabcdefghijklmnop']]);

    $this->redirectUri = 'https://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org/';
    $this->codeVerifier = str_repeat('a', 64);
    $this->codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $this->codeVerifier, true)), '+/', '-_'), '=');
});

it('sends a guest to the login flow', function () {
    $this->get(route('extension.authorize.show', [
        'redirect_uri' => $this->redirectUri,
        'state' => 'state-123',
        'code_challenge' => $this->codeChallenge,
        'code_challenge_method' => 'S256',
    ]))->assertRedirect(route('auth.redirect'));
});

it('refuses a redirect uri that is not allowlisted', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('extension.authorize.show', [
        'redirect_uri' => 'https://evil.example.com/',
        'state' => 'state-123',
        'code_challenge' => $this->codeChallenge,
        'code_challenge_method' => 'S256',
    ]))->assertSessionHasErrors('redirect_uri');
});

it('shows the consent screen to a logged in user', function () {
    $user = User::factory()->create(['given_name' => 'Tomas', 'family_name' => 'van Rijsse']);

    $this->actingAs($user);

    $this->get(route('extension.authorize.show', [
        'redirect_uri' => $this->redirectUri,
        'state' => 'state-123',
        'code_challenge' => $this->codeChallenge,
        'code_challenge_method' => 'S256',
    ]))
        ->assertOk()
        ->assertSee('Tomas van Rijsse')
        ->assertSee('extension/authorize?', false);
});

it('redirects to the extension with a code when approved', function () {
    $this->actingAs(User::factory()->create());

    $approveUrl = URL::temporarySignedRoute('extension.authorize.approve', now()->addMinutes(5), [
        'redirect_uri' => $this->redirectUri,
        'state' => 'state-123',
        'code_challenge' => $this->codeChallenge,
        'code_challenge_method' => 'S256',
    ]);

    $response = $this->post($approveUrl);

    $location = $response->headers->get('Location');

    expect($location)->toStartWith($this->redirectUri.'?');

    parse_str(parse_url((string) $location, PHP_URL_QUERY) ?: '', $query);

    expect($query['state'])->toEqual('state-123');
    expect($query['code'])->toHaveLength(64);
});

it('refuses to approve without a valid signature', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('extension.authorize.approve', [
        'redirect_uri' => $this->redirectUri,
        'state' => 'state-123',
        'code_challenge' => $this->codeChallenge,
        'code_challenge_method' => 'S256',
    ]))->assertForbidden();
});
