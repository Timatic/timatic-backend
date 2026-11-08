<?php

use App\Models\Integration;
use App\Models\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The schema changes commit the test transaction in MySQL, so the rows are removed by hand
afterEach(function () {
    Integration::query()->delete();
});

it('moves archived Bitbucket repositories to ignored repositories', function () {
    // Given
    $migration = require base_path('integrations/bitbucket/database/migrations/2026_11_08_000002_move_archived_bitbucket_repositories_to_ignored_repositories.php');
    $migration->down();
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    DB::table('bitbucket_repository_mappings')->insert([
        ['integration_id' => $integration->id, 'workspace_slug' => 'acme', 'repository_slug' => 'legacy', 'repository_name' => 'Legacy', 'is_archived' => true],
        ['integration_id' => $integration->id, 'workspace_slug' => 'acme', 'repository_slug' => 'shop', 'repository_name' => 'Shop', 'is_archived' => false],
    ]);

    // When
    $migration->up();

    // Then
    expect(Repository::get(['integration_id', 'identifier', 'name', 'is_ignored'])->toArray())
        ->toBe([['integration_id' => $integration->id, 'identifier' => 'legacy', 'name' => 'Legacy', 'is_ignored' => true]])
        ->and(Schema::hasColumn('bitbucket_repository_mappings', 'is_archived'))->toBeFalse()
        ->and(DB::table('bitbucket_repository_mappings')->count())->toBe(2);
});
