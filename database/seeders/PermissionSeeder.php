<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private array $permissions = [
        'user',
        'entries.update_from_others',
        'entries.read',
        'entries.create',
        'entries.mark-as-invoiced',
        'events.create',
        'overtimes.read',
        'users.impersonate',
        'budgets.create',
        'budgets.update',
        'corrections.create',
        'corrections.update',
        'entries.create_for_others',
        'entries.update_from_previous_month',
        'overtimes.approve',
        'budgets.read',
        'budget-types.read',
        'customers.read',
        'customers.update',
        'entry-suggestions.read',
        'entry-suggestions.delete',
        'teams.read',
        'teams.create',
        'teams.update',
        'teams.delete',
        'users.read',
        'overtimes.mark-as-exported',
        'users.create',
        'users.update',
        'users.delete',
        'admin_panel.access',
    ];

    public function run(): void
    {
        Permission::unguard();

        foreach ($this->permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Permission::reguard();
    }
}
