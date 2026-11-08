<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The mapping only holds the mapping; whether a repository is ignored lives in the core repositories table
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('github_repository_mappings')
            ->where('is_archived', true)
            ->orderBy('id')
            ->each(fn (object $mapping) => DB::table('repositories')->insertOrIgnore([
                'integration_id' => $mapping->integration_id,
                'identifier' => $mapping->repository_full_name,
                'name' => $mapping->repository_name,
                'is_ignored' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

        Schema::table('github_repository_mappings', function (Blueprint $table) {
            $table->dropColumn('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('github_repository_mappings', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false);
        });

        DB::table('repositories')
            ->where('is_ignored', true)
            ->orderBy('id')
            ->each(fn (object $repository) => DB::table('github_repository_mappings')
                ->where('integration_id', $repository->integration_id)
                ->where('repository_full_name', $repository->identifier)
                ->update(['is_archived' => true]));
    }
};
