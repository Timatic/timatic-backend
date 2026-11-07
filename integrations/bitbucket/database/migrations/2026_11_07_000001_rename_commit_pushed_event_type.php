<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        EventType::query()->whereKey('commit_pushed')->update(['id' => 'commit_created', 'name' => 'Commit created']);
    }

    public function down(): void
    {
        EventType::query()->whereKey('commit_created')->update(['id' => 'commit_pushed', 'name' => 'Commit pushed']);
    }
};
