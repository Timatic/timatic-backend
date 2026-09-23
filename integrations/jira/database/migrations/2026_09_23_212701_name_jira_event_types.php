<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $names = [
        'issue_changed_to_done' => 'Jira issue changed to done',
        'issue_worklog_created' => 'Jira worklog created',
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
