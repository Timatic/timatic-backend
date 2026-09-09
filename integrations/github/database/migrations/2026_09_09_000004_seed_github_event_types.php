<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** @var array<string, int> */
    private array $eventTypes = [
        'pr_opened' => 50,
        'issue_commented' => 40,
        'branch_created' => 10,
        'tag_created' => 10,
        'repository_created' => 10,
        'repository_deleted' => 10,
        'repository_archived' => 10,
        'repository_unarchived' => 10,
        'repository_publicized' => 10,
        'repository_privatized' => 10,
        'repository_edited' => 10,
        'repository_renamed' => 10,
        'repository_transferred' => 10,
    ];

    public function up(): void
    {
        foreach ($this->eventTypes as $id => $weight) {
            $eventType = EventType::firstOrNew(['id' => $id]);
            $eventType->weight = $weight;
            $eventType->save();
        }
    }

    public function down(): void
    {
        EventType::whereIn('id', array_keys($this->eventTypes))->delete();
    }
};
