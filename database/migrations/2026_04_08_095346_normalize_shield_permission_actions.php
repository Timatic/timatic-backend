<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var string[] */
    private array $resources = ['roles', 'integrations'];

    public function up(): void
    {
        foreach ($this->resources as $resource) {
            DB::table('permissions')->where('name', "{$resource}.view-any")->update(['name' => "{$resource}.read"]);
            DB::table('permissions')->whereIn('name', [
                "{$resource}.view",
                "{$resource}.delete-any",
                "{$resource}.restore-any",
                "{$resource}.force-delete-any",
            ])->delete();
        }
    }

    public function down(): void
    {
        foreach ($this->resources as $resource) {
            DB::table('permissions')->where('name', "{$resource}.read")->update(['name' => "{$resource}.view-any"]);
            DB::table('permissions')->insert([
                ['name' => "{$resource}.view", 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
                ['name' => "{$resource}.delete-any", 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
                ['name' => "{$resource}.restore-any", 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
                ['name' => "{$resource}.force-delete-any", 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }
};
