<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Timatic\ClaudeCode\ServiceProvider;

return new class extends Migration
{
    /**
     * The plugin now titles events "<folder>: <summary>" instead of "Claude Code: <folder>", which
     * told the activities of a day apart by nothing; older events and activities get the same title.
     */
    public function up(): void
    {
        foreach (['events', 'activities'] as $table) {
            DB::table($table)
                ->where('source_id', ServiceProvider::SOURCE_ID)
                ->where('title', 'like', 'Claude Code: %')
                ->whereNotNull('description')
                ->where('description', '!=', '')
                ->update(['title' => DB::raw("LEFT(CONCAT(SUBSTRING(title, 14), ': ', description), 255)")]);
        }
    }

    public function down(): void
    {
        // The new titles can't be told apart from ones the plugin sent itself, so they stay.
    }
};
