<?php

use App\Models\Source;
use Illuminate\Database\Migrations\Migration;
use Timatic\Jira\ServiceProvider;

return new class extends Migration
{
    public function up(): void
    {
        Source::firstOrCreate(['id' => ServiceProvider::SOURCE_ID], ['title' => 'Jira']);
    }

    public function down(): void
    {
        Source::where('id', ServiceProvider::SOURCE_ID)->delete();
    }
};
