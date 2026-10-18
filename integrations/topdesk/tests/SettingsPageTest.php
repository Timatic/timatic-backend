<?php

use App\Models\Integration;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Timatic\Topdesk\Filament\Pages\SettingsPage;

uses(RefreshDatabase::class);

it('saves the integration title', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super_admin'));
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
    $integration = Integration::factory()->create(['type' => 'topdesk', 'config' => []]);

    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->fillForm([
            'title' => 'TOPdesk Acme',
            'base_url' => 'https://acme.topdesk.net',
            'username' => 'u',
            'api_token' => 't',
        ])
        ->callAction('save');

    expect($integration->fresh()->title)->toBe('TOPdesk Acme');
});
