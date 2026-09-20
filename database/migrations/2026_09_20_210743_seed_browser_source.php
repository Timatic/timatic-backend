<?php

use App\Models\Source;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Source::firstOrCreate(['id' => 'browser'], ['title' => 'Browser']);
    }

    public function down(): void
    {
        Source::where('id', 'browser')->delete();
    }
};
