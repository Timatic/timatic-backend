<?php

use App\Models\ApiClient;
use App\Models\EventType;
use App\Models\Source;
use Database\Seeders\ApiClientSeeder;
use Database\Seeders\EventTypeSeeder;
use Database\Seeders\SourceSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The data lives in the seeders; this only makes sure a deployment picks up what was added
     * for the Claude Code plugin. Every seeder is idempotent, so running them again is harmless.
     */
    public function up(): void
    {
        (new SourceSeeder)->run();
        (new ApiClientSeeder)->run();
        (new EventTypeSeeder)->run();
    }

    public function down(): void
    {
        EventType::query()->whereKey(['claude_prompt_submitted', 'claude_question_answered'])->delete();
        ApiClient::query()->whereKey('claude-code')->delete();
        Source::query()->whereKey('claude_code')->delete();
    }
};
