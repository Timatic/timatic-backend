<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('identifier');
            $table->string('name');
            $table->boolean('is_ignored')->default(false);
            $table->timestamp('last_work_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
            $table->unique(['integration_id', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
