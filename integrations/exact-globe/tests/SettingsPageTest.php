<?php

use App\Models\Integration;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\LoginUser;
use Timatic\ExactGlobe\Filament\Pages\SettingsPage;

uses(LoginUser::class);

it('renames the integration', function () {
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::factory()->create(['type' => 'exact-globe']);

    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->fillForm(['title' => 'Exact Globe'])
        ->callAction('save')
        ->assertNotified();

    expect($integration->refresh()->title)->toBe('Exact Globe');
});

it('deletes the integration', function () {
    $this->loginUser(permissions: ['integrations.read', 'integrations.delete']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::factory()->create(['type' => 'exact-globe']);

    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->callAction(DeleteAction::class);

    expect(Integration::find($integration->id))->toBeNull();
});
