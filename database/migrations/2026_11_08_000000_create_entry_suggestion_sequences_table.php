<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_suggestion_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_suggestion_id')->constrained()->cascadeOnDelete();
            $table->string('source_id');
            $table->string('external_id');
            $table->unique(['entry_suggestion_id', 'source_id', 'external_id'], 'entry_suggestion_sequences_unique');
            $table->index(['source_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_suggestion_sequences');
    }
};
