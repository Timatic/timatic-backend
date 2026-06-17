<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $renames = [
        'ViewAny:Role' => 'roles.view-any',
        'View:Role' => 'roles.view',
        'Create:Role' => 'roles.create',
        'Update:Role' => 'roles.update',
        'Delete:Role' => 'roles.delete',
        'DeleteAny:Role' => 'roles.delete-any',
        'Restore:Role' => 'roles.restore',
        'ForceDelete:Role' => 'roles.force-delete',
        'ForceDeleteAny:Role' => 'roles.force-delete-any',
        'RestoreAny:Role' => 'roles.restore-any',
        'Replicate:Role' => 'roles.replicate',
        'Reorder:Role' => 'roles.reorder',
        'ViewAny:Integration' => 'integrations.view-any',
        'View:Integration' => 'integrations.view',
        'Create:Integration' => 'integrations.create',
        'Update:Integration' => 'integrations.update',
        'Delete:Integration' => 'integrations.delete',
        'DeleteAny:Integration' => 'integrations.delete-any',
        'Restore:Integration' => 'integrations.restore',
        'ForceDelete:Integration' => 'integrations.force-delete',
        'ForceDeleteAny:Integration' => 'integrations.force-delete-any',
        'RestoreAny:Integration' => 'integrations.restore-any',
        'Replicate:Integration' => 'integrations.replicate',
        'Reorder:Integration' => 'integrations.reorder',
    ];

    public function up(): void
    {
        foreach ($this->renames as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        foreach ($this->renames as $old => $new) {
            DB::table('permissions')->where('name', $new)->update(['name' => $old]);
        }
    }
};
