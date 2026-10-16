<?php

use App\Models\ApiClient;
use App\Models\EventType;
use App\Models\Source;
use Illuminate\Database\Migrations\Migration;
use Timatic\ClaudeCode\Database\Seeders\ClaudeCodeSeeder;
use Timatic\ClaudeCode\ServiceProvider;

return new class extends Migration
{
    /**
     * The data lives in ClaudeCodeSeeder; this only makes sure a deployment has it. The seeder is
     * idempotent, so a deployment that already has the rows from before this package keeps them.
     */
    public function up(): void
    {
        (new ClaudeCodeSeeder)->run();
    }

    public function down(): void
    {
        EventType::query()->whereKey([
            ServiceProvider::EVENT_TYPE_PROMPT_SUBMITTED,
            ServiceProvider::EVENT_TYPE_QUESTION_ANSWERED,
        ])->delete();
        ApiClient::query()->whereKey(ServiceProvider::CLIENT_ID)->delete();
        Source::query()->whereKey(ServiceProvider::SOURCE_ID)->delete();
    }
};
