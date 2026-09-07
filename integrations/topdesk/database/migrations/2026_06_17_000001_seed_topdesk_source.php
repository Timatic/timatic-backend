<?php

use App\Models\Source;
use Illuminate\Database\Migrations\Migration;
use Timatic\Topdesk\ServiceProvider;

return new class extends Migration
{
    public function up(): void
    {
        Source::firstOrCreate(['id' => ServiceProvider::SOURCE_ID], ['title' => 'TOPdesk']);
    }

    public function down(): void
    {
        Source::where('id', ServiceProvider::SOURCE_ID)->delete();
    }
};
