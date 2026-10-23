<?php

use App\Models\Integration;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\LoginUser;
use Timatic\GitHub\Filament\Pages\PeoplePage;
use Timatic\GitHub\Models\CommitEmail;
use Timatic\GitHub\Models\UserLogin;

uses(LoginUser::class);

it('links a github login and commit emails to a user', function () {
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => []]);
    $user = User::factory()->create();
    CommitEmail::create(['user_id' => $user->id, 'email' => 'old@example.com']);

    Livewire::test(PeoplePage::class, ['record' => $integration->id])
        ->callAction(TestAction::make('edit')->table($user), [
            'login' => 'octocat',
            'emails' => ['Dev@Example.com', 'dev@home.example'],
        ])
        ->assertHasNoFormErrors();

    expect(UserLogin::query()->where('user_id', $user->id)->value('login'))->toBe('octocat')
        ->and(CommitEmail::query()->where('user_id', $user->id)->orderBy('email')->pluck('email')->all())
        ->toBe(['dev@example.com', 'dev@home.example']);
});
