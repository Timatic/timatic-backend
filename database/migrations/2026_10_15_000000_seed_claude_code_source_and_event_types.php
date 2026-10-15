<?php

use App\Models\EventType;
use App\Models\Source;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Mirrors EventTypeSeeder, which explains the weights.
     *
     * @var array<string, array{name: string, weight: int}>
     */
    private array $eventTypes = [
        'claude_prompt_submitted' => ['name' => 'Claude Code prompt', 'weight' => 20],
        'claude_question_answered' => ['name' => 'Claude Code question answered', 'weight' => 20],
    ];

    public function up(): void
    {
        Source::firstOrCreate(['id' => 'claude_code'], ['title' => 'Claude Code']);

        foreach ($this->eventTypes as $id => $attributes) {
            EventType::query()->updateOrCreate(['id' => $id], $attributes);
        }
    }

    public function down(): void
    {
        EventType::whereIn('id', array_keys($this->eventTypes))->delete();

        Source::where('id', 'claude_code')->delete();
    }
};
