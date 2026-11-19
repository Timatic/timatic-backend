<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->string('external_source')->nullable()->after('entry_suggestion_id');
            $table->string('external_id')->nullable()->after('external_source');
            $table->unique(['external_source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropUnique(['external_source', 'external_id']);
            $table->dropColumn(['external_source', 'external_id']);
        });
    }
};
