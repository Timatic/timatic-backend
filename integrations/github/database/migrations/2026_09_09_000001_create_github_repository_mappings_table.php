<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_repository_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('installation_id');
            $table->string('owner_login');
            $table->string('repository_name');
            $table->string('repository_full_name');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->unique(['integration_id', 'repository_full_name'], 'gh_repo_mappings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_repository_mappings');
    }
};
