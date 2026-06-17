<?php

use App\Events\SocialiteRedirecting;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

it('dispatches the SocialiteRedirecting event', function () {
    Event::fake([SocialiteRedirecting::class]);

    $mock = Mockery::mock(GoogleProvider::class);
    $mock->shouldReceive('scopes')->andReturnSelf();
    $mock->shouldReceive('with')->andReturnSelf();
    $mock->shouldReceive('redirect->getTargetUrl')->andReturn('https://accounts.google.com/oauth2/auth');

    Socialite::shouldReceive('driver')->andReturn($mock);

    $this->get(route('auth.redirect'));

    Event::assertDispatched(SocialiteRedirecting::class);
});

it('passes scopes added by listeners to the socialite driver', function () {
    Event::listen(SocialiteRedirecting::class, function (SocialiteRedirecting $event): void {
        $event->addScopes('https://www.googleapis.com/auth/calendar.readonly');
    });

    $capturedScopes = [];

    $mock = Mockery::mock(GoogleProvider::class);
    $mock->shouldReceive('scopes')
        ->once()
        ->withArgs(function (array $scopes) use (&$capturedScopes): bool {
            $capturedScopes = $scopes;

            return true;
        })
        ->andReturnSelf();
    $mock->shouldReceive('with')->andReturnSelf();
    $mock->shouldReceive('redirect->getTargetUrl')->andReturn('https://accounts.google.com/oauth2/auth');

    Socialite::shouldReceive('driver')->andReturn($mock);

    $this->get(route('auth.redirect'));

    expect($capturedScopes)->toContain('https://www.googleapis.com/auth/calendar.readonly');
});

it('does not call scopes when no listener adds any', function () {
    Event::fake([SocialiteRedirecting::class]);

    $mock = Mockery::mock(GoogleProvider::class);
    $mock->shouldReceive('scopes')->never();
    $mock->shouldReceive('with')->andReturnSelf();
    $mock->shouldReceive('redirect->getTargetUrl')->andReturn('https://accounts.google.com/oauth2/auth');

    Socialite::shouldReceive('driver')->andReturn($mock);

    $this->get(route('auth.redirect'));
});
