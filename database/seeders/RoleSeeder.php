<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSuperAdminRole();
        $this->seedAdminRole();
        $this->seedFinanceRole();
        $this->seedAccountManagerRole();
        $this->seedEmployeeRole();
        $this->seedTeamLeadRole();
        $this->grantAdminSectionReadPermissions();
    }

    private function seedSuperAdminRole(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function seedAdminRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::where('guard_name', 'web')->get());
    }

    private function seedFinanceRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);
        $role->syncPermissions([
            'user',
            'entries.read',
            'entries.create',
            'entries.update_from_others',
            'entries.update_from_previous_month',
            'entries.mark-as-invoiced',
            'budgets.read',
        ]);
    }

    private function seedAccountManagerRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'account_manager', 'guard_name' => 'web']);
        $role->syncPermissions([
            'user',
            'budgets.create',
            'budgets.update',
            'budgets.read',
        ]);
    }

    private function seedEmployeeRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $role->syncPermissions([
            'user',
            'entries.read',
            'entries.create',
            'events.create',
            'entry-suggestions.read',
            'entry-suggestions.delete',
            'budgets.read',
            'budget-types.read',
            'overtimes.read',
            'customers.read',
            'teams.read',
            'users.read',
            'tracked-domains.read',
            'tracked-domains.create',
        ]);
    }

    private function seedTeamLeadRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'team_lead', 'guard_name' => 'web']);
        $role->syncPermissions([
            'user',
            'overtimes.approve',
        ]);
    }

    // Every role can see the admin section; only the admin role can change it
    private function grantAdminSectionReadPermissions(): void
    {
        Role::query()->each(fn (Role $role) => $role->givePermissionTo([
            'users.read',
            'roles.read',
            'permissions.read',
            'customers.read',
        ]));
    }
}
