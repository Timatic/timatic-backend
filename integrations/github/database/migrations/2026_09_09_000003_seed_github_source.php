<?php

use App\Models\Source;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Source::firstOrCreate(['id' => 'github'], ['title' => 'GitHub']);
    }

    public function down(): void
    {
        Source::where('id', 'github')->delete();
    }
};
