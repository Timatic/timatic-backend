<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitbucket_repository_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('workspace_slug');
            $table->string('repository_slug');
            $table->string('repository_name');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['integration_id', 'workspace_slug', 'repository_slug'], 'bb_repo_mappings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitbucket_repository_mappings');
    }
};
