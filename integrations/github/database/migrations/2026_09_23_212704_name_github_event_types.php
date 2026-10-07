<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $names = [
        'pr_opened' => 'Pull request opened',
        'issue_commented' => 'Issue commented',
        'branch_created' => 'Branch created',
        'tag_created' => 'Tag created',
        'repository_created' => 'Repository created',
        'repository_deleted' => 'Repository deleted',
        'repository_archived' => 'Repository archived',
        'repository_unarchived' => 'Repository unarchived',
        'repository_publicized' => 'Repository made public',
        'repository_privatized' => 'Repository made private',
        'repository_edited' => 'Repository edited',
        'repository_renamed' => 'Repository renamed',
        'repository_transferred' => 'Repository transferred',
    ];

    public function up(): void
    {
        foreach ($this->names as $id => $name) {
            EventType::query()->whereKey($id)->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        EventType::query()->whereKey(array_keys($this->names))->update(['name' => null]);
    }
};
