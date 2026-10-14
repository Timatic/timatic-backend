<?php

use App\Filament\Resources\Integrations\Pages\ListIntegrations;
use App\Models\Integration;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('creates an integration without a name', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super_admin'));
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    Livewire::test(ListIntegrations::class)
        ->callAction(CreateAction::class, ['type' => 'exact-globe'])
        ->assertHasNoActionErrors();

    expect(Integration::sole())
        ->type->toBe('exact-globe')
        ->title->toBeNull();
});

it('titles an unnamed integration after its type', function () {
    $integration = Integration::factory()->create(['title' => null, 'type' => 'exact-globe']);

    expect($integration->display_title)->toBe('Exact Globe');
});
