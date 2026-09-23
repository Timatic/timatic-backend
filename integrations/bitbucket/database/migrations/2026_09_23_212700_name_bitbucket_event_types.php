<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $names = [
        'commit_pushed' => 'Commit pushed',
        'pr_commented' => 'Pull request commented',
        'pr_approved' => 'Pull request approved',
        'pr_changes_requested' => 'Changes requested on pull request',
        'pr_merged' => 'Pull request merged',
        'pr_declined' => 'Pull request declined',
        'rebase' => 'Branch rebased',
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
