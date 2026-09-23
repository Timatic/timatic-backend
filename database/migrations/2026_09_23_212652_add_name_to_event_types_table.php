<?php

use Database\Seeders\EventTypeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_types', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('id');
        });

        (new EventTypeSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('event_types', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
