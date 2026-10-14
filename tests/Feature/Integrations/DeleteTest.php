<?php

use App\Models\Integration;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Timatic\Rework\Filament\Pages\SettingsPage;

it('deletes an integration from its settings page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super_admin'));
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
    $integration = Integration::factory()->create(['type' => 'rework', 'config' => []]);

    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->callAction(DeleteAction::class)
        ->assertRedirect();

    expect(Integration::find($integration->id))->toBeNull();
});
